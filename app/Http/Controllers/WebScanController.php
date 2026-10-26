<?php

namespace App\Http\Controllers;

use App\Jobs\RunWebScan;
use App\Providers\AppServiceProvider;
use App\Scanning\Store\EphemeralScanStore;
use App\Scanning\Web\ArchiveExtractor;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebScanController extends Controller
{
    public function store(Request $request, EphemeralScanStore $store, Filesystem $files): JsonResponse
    {
        abort_unless(AppServiceProvider::isWebMode(), 404);

        $validated = $request->validate([
            'archive' => ['required', 'file', 'max:'.((int) config('checkmycode.web.max_upload_mb', 50) * 1024)],
            'name' => ['required', 'string', 'max:120'],
            'exclusions' => ['array', 'max:100'],
            'exclusions.*' => ['string', 'max:200'],
        ]);

        $exclusions = array_values($validated['exclusions'] ?? []);
        $projectName = Str::of($validated['name'])->replaceMatches('/[^\w.\- ]/u', '')->trim()->limit(80, '')->toString() ?: 'project';
        $workspace = rtrim(config('checkmycode.web.workspace_path'), '/').'/'.Str::random(32);

        try {
            $count = ArchiveExtractor::fromConfig()->extract($request->file('archive')->getRealPath(), $workspace.'/src');
        } catch (\RuntimeException $e) {
            $files->deleteDirectory($workspace);

            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            @unlink($request->file('archive')->getRealPath());
        }

        if ($count === 0) {
            $files->deleteDirectory($workspace);

            return response()->json(['message' => 'No scannable files were found in the selection.'], 422);
        }

        $id = EphemeralScanStore::create($projectName, $exclusions);
        EphemeralScanStore::appendLog($id, "Received {$count} files for {$projectName}. Waiting for a scanner...");
        $store->remember($id);

        RunWebScan::dispatch($id, $workspace, $exclusions);

        return response()->json(['id' => $id]);
    }
}
