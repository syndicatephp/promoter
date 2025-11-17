<?php

namespace Syndicate\Promoter\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

class MakeSeoConfig extends Command
{
    protected $signature = 'make:seo
                            {model : The fully qualified model class name}';

    protected $description = 'Create a SeoConfig class for the given model and attach HasSeo trait';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $model = ltrim($this->argument('model'), '\\');

        if (!class_exists($model)) {
            $this->error("Model [{$model}] does not exist.");
            return static::FAILURE;
        }

        $reflection = new ReflectionClass($model);
        $shortName = $reflection->getShortName();          // e.g. Test
        $modelNamespace = $reflection->getNamespaceName(); // e.g. Subsites\Think\Content\Models

        $namespaceParts = explode('\\', $modelNamespace);
        $modelsIndex = array_search('Models', $namespaceParts, true);

        if ($modelsIndex === false) {
            $this->error('Could not determine root namespace (no "Models" segment found).');
            return static::FAILURE;
        }

        // Root namespace: everything before "Models"
        $rootParts = array_slice($namespaceParts, 0, $modelsIndex); // [Subsites, Think, Content]

        // SeoConfig namespace: Root + Syndicate\Promoter\Seo
        $seoNamespaceParts = array_merge($rootParts, ['Syndicate', 'Promoter', 'Seo']);
        $seoNamespace = implode('\\', $seoNamespaceParts);

        $className = $shortName.'SeoConfig'; // e.g. TestSeoConfig

        // Build target directory based on model file location
        $modelDir = dirname($reflection->getFileName());     // .../Subsites/Think/Content/Models
        $rootDir = dirname($modelDir);                      // .../Subsites/Think/Content
        $targetDir = $rootDir.'/Syndicate/Promoter/Seo';      // .../Subsites/Think/Content/Syndicate/Promoter/Seo

        if (!$this->files->isDirectory($targetDir)) {
            $this->files->makeDirectory($targetDir, 0755, true);
        }

        $path = $targetDir.'/'.$className.'.php';

        if ($this->files->exists($path)) {
            $this->error("SeoConfig already exists at [{$path}].");
            return static::FAILURE;
        }

        $stub = $this->buildClass(
            namespace: $seoNamespace,
            className: $className,
            modelFqn: $model,
            modelAlias: $shortName,
        );

        $this->files->put($path, $stub);

        $this->info("SeoConfig created: {$path}");

        $this->addHasSeoTraitToModel($reflection);

        return static::SUCCESS;
    }


    protected function buildClass(string $namespace, string $className, string $modelFqn, string $modelAlias): string
    {
        $stub = $this->files->get($this->stubPath());

        return str_replace(
            [
                '{{ namespace }}',
                '{{ class }}',
                '{{ model_fqn }}',
                '{{ model_alias }}',
            ],
            [
                $namespace,
                $className,
                $modelFqn,
                $modelAlias,
            ],
            $stub
        );
    }

    protected function stubPath(): string
    {
        $published = base_path('stubs/promoter/seo-config.stub');

        if (file_exists($published)) {
            return $published;
        }

        return __DIR__.'/../../../../stubs/promoter/seo-config.stub';
    }

    protected function addHasSeoTraitToModel(ReflectionClass $model): void
    {
        $path = $model->getFileName();

        if (!$path || !$this->files->exists($path)) {
            $this->warn('Could not locate model file to add HasSeo.');
            return;
        }

        $contents = $this->files->get($path);

        // If trait already referenced, bail out
        if (str_contains($contents, 'HasSeo')) {
            // naive but good enough to avoid duplicates
            return;
        }

        $lines = preg_split("/(\r\n|\n|\r)/", $contents);
        if ($lines === false) {
            return;
        }

        // 1) Add use Syndicate\Promoter\Concerns\HasSeo;
        $lines = $this->ensureHasSeoImport($lines);

        // 2) Add "use HasSeo;" inside the class
        $lines = $this->ensureHasSeoTraitUsage($lines);

        $newContents = implode(PHP_EOL, $lines);
        $this->files->put($path, $newContents);

        $this->info('HasSeo trait added to model ['.$model->getName().'].');
    }

    protected function ensureHasSeoImport(array $lines): array
    {
        $import = 'use Syndicate\Promoter\Traits\HasSeo;';

        // Already imported?
        foreach ($lines as $line) {
            if (trim($line) === $import) {
                return $lines;
            }
        }

        $namespaceIndex = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^namespace\s+.+;$/', trim($line))) {
                $namespaceIndex = $i;
                break;
            }
        }

        if ($namespaceIndex === null) {
            // no namespace? just prepend import (very edge case)
            array_unshift($lines, $import, '');
            return $lines;
        }

        // Find position after last existing "use" block after namespace
        $insertIndex = $namespaceIndex + 1;
        for ($i = $namespaceIndex + 1; $i < count($lines); $i++) {
            $trim = trim($lines[$i]);

            if ($trim === '') {
                // skip empty lines right after namespace
                $insertIndex = $i + 1;
                continue;
            }

            if (str_starts_with($trim, 'use ')) {
                $insertIndex = $i + 1;
                continue;
            }

            // hit something else (class, comment, etc)
            break;
        }

        array_splice($lines, $insertIndex, 0, $import);

        return $lines;
    }

    protected function ensureHasSeoTraitUsage(array $lines): array
    {
        // If there's already a "use HasSeo;" in a class, bail
        foreach ($lines as $line) {
            if (preg_match('/^\s*use\s+HasSeo\s*;$/', $line)) {
                return $lines;
            }
        }

        // Find the class line
        $classIndex = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^(abstract\s+|final\s+)?class\s+\w+/m', trim($line))) {
                $classIndex = $i;
                break;
            }
        }

        if ($classIndex === null) {
            // no class found; do nothing
            return $lines;
        }

        // Find the line with the opening brace for the class
        $braceIndex = $classIndex;
        for ($i = $classIndex; $i < count($lines); $i++) {
            if (str_contains($lines[$i], '{')) {
                $braceIndex = $i;
                break;
            }
        }

        // Insert "use HasSeo;" after the opening brace line
        $indent = '    '; // 4 spaces
        $traitLine = $indent.'use HasSeo;';

        array_splice($lines, $braceIndex + 1, 0, [$traitLine, '']);

        return $lines;
    }
}
