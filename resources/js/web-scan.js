/**
 * Web mode: pick a folder or files in the browser, drop excluded folders
 * before anything leaves the machine, zip the rest and upload it for a
 * one-off scan. Used by the Scanner Livewire component.
 */
import { zip } from 'fflate';

// Media and archives never matter to the scanners; skipping them keeps uploads small.
const SKIPPED_EXTENSIONS = new Set([
    'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'bmp', 'tiff', 'psd', 'svgz',
    'mp3', 'mp4', 'mov', 'avi', 'webm', 'wav', 'ogg', 'flac',
    'woff', 'woff2', 'ttf', 'otf', 'eot',
    'zip', 'tar', 'gz', 'tgz', 'bz2', 'xz', '7z', 'rar', 'dmg', 'iso',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'exe', 'dll', 'so', 'dylib', 'o', 'a', 'class', 'pyc', 'wasm',
    'sqlite', 'sqlite3', 'db', 'log', 'map',
]);
const MAX_FILE_BYTES = 5 * 1024 * 1024;

function normalizeExclusion(exclusion) {
    return String(exclusion).trim().replace(/^\/+|\/+$/g, '');
}

export function isExcluded(path, exclusions) {
    const segments = path.split('/');
    if (segments.includes('.git')) {
        return true;
    }

    return exclusions.some((raw) => {
        const exclusion = normalizeExclusion(raw);
        if (!exclusion) {
            return false;
        }
        if (exclusion.includes('/')) {
            return path === exclusion || path.startsWith(exclusion + '/');
        }

        return segments.includes(exclusion);
    });
}

function isSkippedFile(path, size) {
    const extension = path.includes('.') ? path.split('.').pop().toLowerCase() : '';

    return size > MAX_FILE_BYTES || SKIPPED_EXTENSIONS.has(extension);
}

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function readDirectoryEntries(reader) {
    return new Promise((resolve, reject) => reader.readEntries(resolve, reject));
}

function entryFile(entry) {
    return new Promise((resolve, reject) => entry.file(resolve, reject));
}

/** Walk a dropped folder, pruning excluded directories instead of reading them. */
async function collectEntry(entry, prefix, exclusions, out) {
    const path = prefix ? `${prefix}/${entry.name}` : entry.name;

    if (isExcluded(path, exclusions)) {
        out.excluded++;
        return;
    }

    if (entry.isFile) {
        out.files.push({ path, file: await entryFile(entry) });
        return;
    }

    const reader = entry.createReader();
    let batch;
    do {
        batch = await readDirectoryEntries(reader);
        for (const child of batch) {
            await collectEntry(child, path, exclusions, out);
        }
    } while (batch.length > 0);
}

/**
 * Walk a folder opened with the File System Access API. Excluded folders are
 * skipped before they are opened, so their contents are never even read.
 */
async function collectHandle(directory, prefix, exclusions, out) {
    for await (const [name, handle] of directory.entries()) {
        const path = prefix ? `${prefix}/${name}` : name;

        if (isExcluded(path, exclusions)) {
            out.excluded++;
            continue;
        }

        if (handle.kind === 'file') {
            out.files.push({ path, file: await handle.getFile() });
        } else {
            await collectHandle(handle, path, exclusions, out);
        }
    }
}

function zipAsync(data) {
    return new Promise((resolve, reject) => {
        zip(data, { level: 6 }, (error, archive) => (error ? reject(error) : resolve(archive)));
    });
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('webScanner', (config) => ({
        allFiles: [],
        files: [],
        name: null,
        prunedCount: 0,
        excludedCount: 0,
        skippedCount: 0,
        totalBytes: 0,
        exclusions: config.exclusions ?? [],
        busy: false,
        phase: '',
        showPrivacy: false,
        showWelcome: false,
        showHowItWorks: false,
        error: null,
        dragging: false,

        init() {
            // First visit in this browser: explain what CheckMyCode is.
            try {
                this.showWelcome = window.localStorage.getItem('checkmycode.welcomed') !== '1';
            } catch (e) {
                this.showWelcome = false;
            }

            this.$wire.on('exclusions-changed', ({ exclusions }) => {
                this.exclusions = exclusions;
                this.applyFilters();
            });
            this.$wire.on('web-selection-cleared', () => {
                if (!this.busy) {
                    this.setSelection(null, [], 0);
                }
            });
        },

        dismissWelcome() {
            this.showWelcome = false;
            try {
                window.localStorage.setItem('checkmycode.welcomed', '1');
            } catch (e) {
                // storage blocked: the modal simply shows again next time
            }
        },

        get selectionSummary() {
            const parts = [`${this.files.length.toLocaleString()} files · ${formatBytes(this.totalBytes)}`];
            if (this.excludedCount + this.skippedCount > 0) {
                parts.push(`${(this.excludedCount + this.skippedCount).toLocaleString()} skipped`);
            }

            return parts.join(' · ');
        },

        /**
         * Chrome/Edge: open the folder read-only with the File System Access
         * API (milder browser prompt, excluded folders never read). Other
         * browsers: the classic folder input.
         */
        async pickFolder() {
            if (this.busy) {
                return;
            }
            if (typeof window.showDirectoryPicker !== 'function') {
                this.$refs.folderInput.click();
                return;
            }

            let directory;
            try {
                directory = await window.showDirectoryPicker({ id: 'checkmycode', mode: 'read' });
            } catch (e) {
                if (e.name !== 'AbortError') {
                    // Blocked (e.g. a system folder) or unsupported here: fall back.
                    this.$refs.folderInput.click();
                }
                return;
            }

            this.busy = true;
            this.phase = 'Reading folder…';
            try {
                const out = { files: [], excluded: 0 };
                await collectHandle(directory, '', this.exclusions, out);
                this.setSelection(directory.name, out.files, out.excluded);
            } catch (e) {
                this.error = 'Could not read that folder. Try dragging it onto the page instead.';
            } finally {
                this.busy = false;
                this.phase = '';
            }
        },

        pickedFolder(event) {
            const picked = Array.from(event.target.files ?? []);
            event.target.value = '';
            if (picked.length === 0) {
                return;
            }

            // webkitRelativePath is "<folder>/<path inside folder>"
            const root = picked[0].webkitRelativePath.split('/')[0];
            const files = picked.map((file) => ({
                path: file.webkitRelativePath.split('/').slice(1).join('/'),
                file,
            }));
            this.setSelection(root, files, 0);
        },

        pickedFiles(event) {
            const picked = Array.from(event.target.files ?? []);
            event.target.value = '';
            if (picked.length === 0) {
                return;
            }

            const name = picked.length === 1 ? picked[0].name : `${picked.length} files`;
            this.setSelection(name, picked.map((file) => ({ path: file.name, file })), 0);
        },

        async dropped(event) {
            this.dragging = false;
            if (this.busy) {
                return;
            }

            const entries = Array.from(event.dataTransfer?.items ?? [])
                .map((item) => (item.webkitGetAsEntry ? item.webkitGetAsEntry() : null))
                .filter(Boolean);
            if (entries.length === 0) {
                return;
            }

            this.busy = true;
            this.phase = 'Reading…';
            try {
                const out = { files: [], excluded: 0 };
                if (entries.length === 1 && entries[0].isDirectory) {
                    // A single folder: its contents become the project root.
                    const reader = entries[0].createReader();
                    let batch;
                    do {
                        batch = await readDirectoryEntries(reader);
                        for (const child of batch) {
                            await collectEntry(child, '', this.exclusions, out);
                        }
                    } while (batch.length > 0);
                    this.setSelection(entries[0].name, out.files, out.excluded);
                } else {
                    for (const entry of entries) {
                        await collectEntry(entry, '', this.exclusions, out);
                    }
                    const name = entries.length === 1 ? entries[0].name : `${entries.length} items`;
                    this.setSelection(name, out.files, out.excluded);
                }
            } catch (e) {
                this.error = 'Could not read the dropped items. Try the Select Folder button instead.';
            } finally {
                this.busy = false;
            }
        },

        setSelection(name, files, prunedCount) {
            this.name = name;
            this.allFiles = files;
            this.prunedCount = prunedCount;
            this.error = null;
            this.applyFilters();
            this.$wire.webSelectionChanged(name);
        },

        applyFilters() {
            let excluded = this.prunedCount ?? 0;
            let skipped = 0;
            const kept = [];

            for (const entry of this.allFiles) {
                if (isExcluded(entry.path, this.exclusions)) {
                    excluded++;
                } else if (isSkippedFile(entry.path, entry.file.size)) {
                    skipped++;
                } else {
                    kept.push(entry);
                }
            }

            this.files = kept;
            this.excludedCount = excluded;
            this.skippedCount = skipped;
            this.totalBytes = kept.reduce((sum, entry) => sum + entry.file.size, 0);
        },

        async upload() {
            if (this.busy || this.files.length === 0) {
                return;
            }
            if (this.files.length > config.maxFiles) {
                this.error = `This selection has ${this.files.length.toLocaleString()} files; the limit is ${config.maxFiles.toLocaleString()}. Add exclusions or scan a sub-folder.`;
                return;
            }

            this.busy = true;
            this.error = null;

            try {
                this.phase = 'Preparing files…';
                const data = {};
                for (const entry of this.files) {
                    data[entry.path] = new Uint8Array(await entry.file.arrayBuffer());
                }
                const archive = await zipAsync(data);

                if (archive.byteLength > config.maxUploadMb * 1024 * 1024) {
                    throw new Error(`The compressed project is ${formatBytes(archive.byteLength)}; the limit is ${config.maxUploadMb} MB. Add exclusions or scan a sub-folder.`);
                }

                const form = new FormData();
                form.append('archive', new Blob([archive], { type: 'application/zip' }), 'project.zip');
                form.append('name', this.name ?? 'project');
                this.exclusions.forEach((exclusion) => form.append('exclusions[]', exclusion));

                const response = await this.send(form);
                await this.$wire.webScanStarted(response.id);
            } catch (e) {
                this.error = e.message || 'Could not send your files to the scanner.';
            } finally {
                this.busy = false;
                this.phase = '';
            }
        },

        send(form) {
            return new Promise((resolve, reject) => {
                const request = new XMLHttpRequest();
                request.open('POST', config.uploadUrl);
                request.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
                request.setRequestHeader('Accept', 'application/json');
                request.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        this.phase = `Sending securely ${Math.round((event.loaded / event.total) * 100)}%`;
                    }
                };
                request.onload = () => {
                    let body = {};
                    try {
                        body = JSON.parse(request.responseText);
                    } catch (e) {
                        // non-JSON error page
                    }
                    if (request.status >= 200 && request.status < 300 && body.id) {
                        resolve(body);
                    } else if (request.status === 429) {
                        reject(new Error('Scan limit reached for now. Please try again later, or run CheckMyCode locally with Docker or the desktop app.'));
                    } else if (request.status === 413) {
                        reject(new Error('This project is too large for the web scanner. Add exclusions, scan a sub-folder, or use the CLI.'));
                    } else {
                        reject(new Error(body.message || `Could not start the scan (HTTP ${request.status}).`));
                    }
                };
                request.onerror = () => reject(new Error('Network error while sending your files. Nothing was kept; please try again.'));
                this.phase = 'Sending securely…';
                request.send(form);
            });
        },
    }));
});
