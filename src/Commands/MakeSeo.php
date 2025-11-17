<?php

namespace Syndicate\Promoter\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Laravel\Prompts\Prompt;
use ReflectionClass;
use function Laravel\Prompts\{confirm, error, info, text, warning};

class MakeSeo extends Command
{
    protected $signature = 'make:syndicate-seo
                            {model? : The fully qualified model class name}
                            {--resource= : The Filament Resource class (FQN or short name under App\\Filament\\Resources)}';

    protected $description = 'Create a SeoConfig class for the given model and attach HasSeo trait';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $interactive = method_exists($this->input, 'isInteractive') ? $this->input->isInteractive() : true;
        Prompt::interactive($interactive);

        // Resolve model argument or prompt for it
        $argModel = $this->argument('model');
        $modelInput = is_string($argModel) ? trim($argModel) : '';

        if ($modelInput === '' && $interactive) {
            $modelInput = (string)text(
                label: 'Enter the model class. Defaults to normal folder structure (e.g., App\\Models\\Post). You may also provide a full FQN (e.g., Domain\\Blog\\Models\\Post):'
            );
            $modelInput = trim((string)$modelInput);
        }

        if ($modelInput === '') {
            error('No model provided. Pass a model argument or run interactively.');
            return static::FAILURE;
        }

        // Normalize to FQN: allow short model names under App\\Models
        $model = ltrim($modelInput, '\\');
        if (class_exists('App\\Models\\' . $model)) {
            $model = 'App\\Models\\' . $model;
        }

        // If still not valid and interactive, re-prompt up to 2 more times
        $attempts = 0;
        while (!class_exists($model) && $interactive && $attempts < 2) {
            warning("Model [{$model}] does not exist.");
            $retry = (string)text(label: 'Please enter a valid model class (short name or FQN):');
            $retry = trim($retry);
            if ($retry === '') {
                break;
            }
            $model = ltrim($retry, '\\');
            if (class_exists('App\\Models\\' . $model)) {
                $model = 'App\\Models\\' . $model;
            }
            $attempts++;
        }

        if (!class_exists($model)) {
            error("Model [{$model}] does not exist.");
            return static::FAILURE;
        }

        $reflection = new ReflectionClass($model);
        $shortName = $reflection->getShortName();          // e.g. Test
        $modelNamespace = $reflection->getNamespaceName(); // e.g. Subsites\\Think\\Content\\Models

        $namespaceParts = explode('\\', $modelNamespace);
        $modelsIndex = array_search('Models', $namespaceParts, true);

        if ($modelsIndex === false) {
            error('Could not determine root namespace (no "Models" segment found).');
            return static::FAILURE;
        }

        // Root namespace: everything before "Models"
        $rootParts = array_slice($namespaceParts, 0, $modelsIndex); // [Subsites, Think, Content]

        // SeoConfig namespace: Root + Syndicate\Promoter\Seo
        $seoNamespaceParts = array_merge($rootParts, ['Syndicate', 'Promoter', 'Seo']);
        $seoNamespace = implode('\\', $seoNamespaceParts);

        $className = $shortName . 'SeoConfig'; // e.g. TestSeoConfig

        // Build target directory based on model file location
        $modelDir = dirname($reflection->getFileName());     // .../Subsites/Think/Content/Models
        $rootDir = dirname($modelDir);                      // .../Subsites/Think/Content
        $targetDir = $rootDir . '/Syndicate/Promoter/Seo';      // .../Subsites/Think/Content/Syndicate/Promoter/Seo

        if (!$this->files->isDirectory($targetDir)) {
            $this->files->makeDirectory($targetDir, 0755, true);
        }

        $path = $targetDir . '/' . $className . '.php';

        if ($this->files->exists($path)) {
            error("SeoConfig already exists at [{$path}].");
            return static::FAILURE;
        }

        $stub = $this->buildClass(
            namespace: $seoNamespace,
            className: $className,
            modelFqn: $model,
            modelAlias: $shortName,
        );

        $this->files->put($path, $stub);

        info("SeoConfig created: {$path}");

        // Ask whether to add the HasSeo trait (defaults to Yes)
        $addTrait = $interactive ? confirm(label: 'Add HasSeo trait to the model?', default: true) : true;
        if ($addTrait) {
            $this->addHasSeoTraitToModel($reflection);
        }

        // Resource page generation handling
        $resourceOption = (string)($this->option('resource') ?? '');
        if ($resourceOption !== '') {
            // Explicit option wins; no prompt needed
            $this->createEditSeoPageForResource(
                modelShortName: $shortName,
                resourceOption: $resourceOption,
            );
        } elseif ($interactive) {
            // Prompt whether to generate the Resource page (defaults to Yes)
            if (confirm(label: 'Generate Filament Edit SEO page for a Resource?', default: true)) {
                $defaultResource = 'App\\Filament\\Resources\\' . $shortName . 'Resource';
                $resourceInput = (string)text(
                    label: 'Enter the Resource class (short name like TestResource or full FQN). Default follows App\\Filament\\Resources convention:',
                    default: $defaultResource
                );
                $resourceInput = trim($resourceInput);
                if ($resourceInput !== '') {
                    $this->createEditSeoPageForResource(
                        modelShortName: $shortName,
                        resourceOption: $resourceInput,
                    );
                }
            }
        }

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

        return __DIR__ . '/../../stubs/seo-config.stub';
    }

    protected function addHasSeoTraitToModel(ReflectionClass $model): void
    {
        $path = $model->getFileName();

        if (!$path || !$this->files->exists($path)) {
            warning('Could not locate model file to add HasSeo.');
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

        info('HasSeo trait added to model [' . $model->getName() . '].');
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
        $traitLine = $indent . 'use HasSeo;';

        array_splice($lines, $braceIndex + 1, 0, [$traitLine, '']);

        return $lines;
    }

    protected function createEditSeoPageForResource(string $modelShortName, string $resourceOption): void
    {
        // Determine the fully qualified resource class
        $resourceClass = ltrim($resourceOption, '\\');

        // Allow short name like "TestResource" under default App\\Filament\\Resources namespace
        if (!class_exists($resourceClass)) {
            $candidate = 'App\\Filament\\Resources\\' . $resourceClass;
            if (class_exists($candidate)) {
                $resourceClass = $candidate;
            }
        }

        if (!class_exists($resourceClass)) {
            error("Resource [{$resourceOption}] does not exist.");
            return;
        }

        $resourceRef = new ReflectionClass($resourceClass);
        $resourceShort = $resourceRef->getShortName(); // e.g. TestResource
        $resourceNamespace = $resourceRef->getNamespaceName(); // e.g. App\\Filament\\Resources

        $resourceDir = dirname($resourceRef->getFileName());
        $targetDir = $resourceDir . '/' . $resourceShort . '/Pages';

        if (!$this->files->isDirectory($targetDir)) {
            $this->files->makeDirectory($targetDir, 0755, true);
        }

        $pageNamespace = $resourceNamespace . '\\' . $resourceShort . '\\Pages';
        $pageClass = 'Edit' . $modelShortName . 'Seo'; // e.g. EditTestSeo
        $pagePath = $targetDir . '/' . $pageClass . '.php';

        if ($this->files->exists($pagePath)) {
            info("EditSeo page already exists: {$pagePath}");
            return;
        }

        $contents = $this->buildEditSeoPageClass(
            namespace: $pageNamespace,
            className: $pageClass,
            resourceFqn: $resourceClass,
        );

        $this->files->put($pagePath, $contents);
        info("EditSeo page created: {$pagePath}");
    }

    protected function buildEditSeoPageClass(string $namespace, string $className, string $resourceFqn): string
    {
        $stub = $this->files->get($this->editSeoStubPath());

        return str_replace(
            [
                '{{ namespace }}',
                '{{ class }}',
                '{{$resource}}',
            ],
            [
                $namespace,
                $className,
                '\\' . ltrim($resourceFqn, '\\'),
            ],
            $stub
        );
    }

    protected function editSeoStubPath(): string
    {
        $published = base_path('stubs/promoter/edit-seo.stub');
        if (file_exists($published)) {
            return $published;
        }
        return __DIR__ . '/../../stubs/edit-seo.stub';
    }
}
