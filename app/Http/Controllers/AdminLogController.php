<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class AdminLogController extends Controller
{
    public function index(Request $request): View
    {
        $logDirectory = storage_path('logs');
        File::ensureDirectoryExists($logDirectory);
        $files = collect(File::files($logDirectory))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'log')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();
        $selectedName = $request->string('file')->toString();
        $selected = $files->first(fn ($file) => $file->getFilename() === $selectedName) ?? $files->first();
        $lineLimit = min(1000, max(50, $request->integer('lines', 300)));
        $content = $selected && File::isFile($selected->getPathname())
            ? collect(preg_split('/\r\n|\n|\r/', File::get($selected->getPathname())) ?: [])->take(-$lineLimit)->implode(PHP_EOL)
            : '';

        return view('admin.logs', [
            'files' => $files,
            'selected' => $selected,
            'content' => $content,
            'lineLimit' => $lineLimit,
        ]);
    }
}
