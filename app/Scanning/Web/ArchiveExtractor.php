<?php

namespace App\Scanning\Web;

use RuntimeException;
use ZipArchive;

/**
 * Extracts an uploaded project archive into a scan workspace, refusing
 * anything that could escape it (zip-slip, absolute paths, symlinks) and
 * enforcing size and file-count limits (zip bombs).
 */
class ArchiveExtractor
{
    public function __construct(
        protected int $maxFiles,
        protected int $maxUncompressedBytes,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            maxFiles: (int) config('checkmycode.web.max_files', 20000),
            maxUncompressedBytes: (int) config('checkmycode.web.max_uncompressed_mb', 200) * 1024 * 1024,
        );
    }

    /**
     * @return int Number of files extracted.
     */
    public function extract(string $archivePath, string $destination): int
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The upload is not a readable archive.');
        }

        try {
            if ($zip->numFiles > $this->maxFiles) {
                throw new RuntimeException("The project has more than {$this->maxFiles} files.");
            }

            $totalBytes = 0;
            $entries = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';

                if (str_ends_with($name, '/')) {
                    continue;
                }

                $relative = static::safeRelativePath($name);
                if ($relative === null) {
                    throw new RuntimeException('The archive contains an unsafe path: '.mb_strimwidth($name, 0, 120, '…'));
                }

                // Git metadata is not needed for a files-only scan.
                if (in_array('.git', explode('/', $relative), true)) {
                    continue;
                }

                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                    && $opsys === ZipArchive::OPSYS_UNIX
                    && (($attributes >> 16) & 0170000) === 0120000) {
                    continue; // symlink: skip
                }

                $totalBytes += (int) $stat['size'];
                if ($totalBytes > $this->maxUncompressedBytes) {
                    throw new RuntimeException('The project is larger than '.intdiv($this->maxUncompressedBytes, 1024 * 1024).' MB once extracted.');
                }

                $entries[$i] = $relative;
            }

            $destination = rtrim($destination, '/');
            if (! is_dir($destination) && ! mkdir($destination, 0700, true)) {
                throw new RuntimeException('Could not create the scan workspace.');
            }

            foreach ($entries as $index => $relative) {
                $target = $destination.'/'.$relative;
                $directory = dirname($target);
                if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                    throw new RuntimeException('Could not create the scan workspace.');
                }

                $source = $zip->getStream($zip->getNameIndex($index));
                $sink = fopen($target, 'wb');
                if ($source === false || $sink === false) {
                    throw new RuntimeException('Could not extract '.$relative);
                }
                // Copy at most the declared size so a lying header cannot inflate the file.
                stream_copy_to_stream($source, $sink, (int) $zip->statIndex($index)['size']);
                fclose($source);
                fclose($sink);
            }

            return count($entries);
        } finally {
            $zip->close();
        }
    }

    /**
     * Normalize an archive entry name to a relative path inside the
     * workspace, or null when it would escape it.
     */
    public static function safeRelativePath(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);

        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null;
            }
            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }
}
