<?php

namespace Napse\BundleMaker\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: 'make:bundle', description: 'Generates a new Symfony bundle with the modern structure.')]
class CreateBundleCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $bundleName = $helper->ask($input, $output, new Question('Enter the Bundle Name: '));
        $vendorName = $helper->ask($input, $output, new Question('Enter the Vendor Name: '));
        $version = $helper->ask($input, $output, new Question('Enter the Bundle Version (default 1): ', '1'));

        if (!$bundleName || !$vendorName) {
            $output->writeln('<error>Bundle Name and Vendor Name are required!</error>');
            return Command::FAILURE;
        }

        $namespace = sprintf('%s\\%s', $vendorName, $bundleName);

        $filesystem = new Filesystem();
        $basePath = Path::normalize("bundles/{$bundleName}");

        $output->writeln("Generating bundle at {$basePath} using the modern structure...");

        // Create necessary directories with .gitkeep files
        $directories = [
            'config',
            'src/Command',
            'src/Controller',
            'src/DependencyInjection',
            'src/EventSubscriber',
            'src/Security',
            'src/Service',
            'public',
            'translations',
            'templates',
            'tests',
            'assets',
        ];

        foreach ($directories as $directory) {
            $dirPath = Path::join($basePath, $directory);
            $filesystem->mkdir($dirPath);
            $filesystem->touch(Path::join($dirPath, '.gitkeep'));
        }

        // Generate key files
        $filesystem->dumpFile(Path::join($basePath, 'composer.json'), $this->generateComposerJson($bundleName, $vendorName, $version));
        $filesystem->dumpFile(Path::join($basePath, 'src', $bundleName . 'Bundle.php'), $this->generateBundleClass($bundleName, $namespace));
        $filesystem->dumpFile(Path::join($basePath, 'src/DependencyInjection', $bundleName . 'Extension.php'), $this->generateContainerExtension($bundleName, $namespace));
        $filesystem->dumpFile(Path::join($basePath, 'config', 'routes.yaml'), "# Add your routes here\n");
        $filesystem->dumpFile(Path::join($basePath, 'config', 'services.yaml'), $this->generateServicesConfig($namespace));
        $filesystem->dumpFile(Path::join($basePath, 'templates', 'index.html.twig'), "{# Twig template #}\n<h1>{$bundleName} Bundle</h1>");
        $filesystem->dumpFile(Path::join($basePath, 'flex-recipe', 'manifest.json'), $this->generateFlexRecipeManifest($bundleName));

        $output->writeln('<info>Bundle generation completed successfully using the modern structure!</info>');

        return Command::SUCCESS;
    }

    private function generateComposerJson(string $bundleName, string $vendorName, string $version): string
    {
        $packageName = strtolower(sprintf('%s/%s', $vendorName, $bundleName));
        $namespace = sprintf('%s\\%s', $vendorName, $bundleName);

        return json_encode([
            'name' => $packageName,
            'description' => "{$bundleName} Symfony Bundle",
            'type' => 'symfony-bundle',
            'autoload' => [
                'psr-4' => [
                    "{$namespace}\\" => 'src/'
                ]
            ],
            'require' => [
                'php' => '^8.4',
                'symfony/framework-bundle' => '^6.4'
            ],
            'version' => $version
        ], JSON_PRETTY_PRINT);
    }

    private function generateBundleClass(string $bundleName, string $namespace): string
    {
        return "<?php\n" .
            "declare(strict_types=1);\n" .
            "namespace {$namespace};\n\n" .
            "use {$namespace}\DependencyInjection\\{$bundleName}Extension;\n" .
            "use Symfony\\Component\\HttpKernel\\Bundle\\Bundle;\n\n" .
            "class {$bundleName}Bundle extends Bundle\n" .
            "{\n" .
            "    protected function getContainerExtensionClass(): string\n" .
            "    {\n" .
            "        return {$bundleName}Extension::class;\n" .
            "    }\n" .
            "}";
    }

    private function generateContainerExtension(string $bundleName, string $namespace): string
    {
        return "<?php\n" .
            "namespace {$namespace}\\DependencyInjection;\n\n" .
            "use Symfony\\Component\\DependencyInjection\\ContainerBuilder;\n" .
            "use Symfony\\Component\\DependencyInjection\\Extension\\Extension;\n" .
            "use Symfony\\Component\\DependencyInjection\\Loader\\YamlFileLoader;\n" .
            "use Symfony\\Component\\Config\\FileLocator;\n\n" .
            "class {$bundleName}Extension extends Extension\n" .
            "{\n" .
            "    public function load(array \$configs, ContainerBuilder \$container)\n" .
            "    {\n" .
            "        \$loader = new YamlFileLoader(\n" .
            "            \$container,\n" .
            "            new FileLocator(__DIR__ . '/../../config')\n" .
            "        );\n" .
            "        \$loader->load('services.yaml');\n" .
            "    }\n" .
            "}";
    }

    private function generateServicesConfig($namespace): string
    {
        return "services:\n" .
            "    _defaults:\n" .
            "        autowire: true\n" .
            "        autoconfigure: true\n" .
            "        public: true\n" .
            "    {$namespace}\\Command\\:\n" .
            "        resource: '../src/Command/*'\n" .
            "        tags: ['console.command']\n" .
            "    {$namespace}\\Service\\:\n" .
            "        resource: '../src/Service/*'\n" .
            "    {$namespace}\\EventSubscriber\\:\n" .
            "        resource: '../src/EventSubscriber/*'\n";
    }

    private function generateFlexRecipeManifest(string $bundleName): string
    {
        return json_encode([
            'bundles' => [
                "{$bundleName}" => ['all' => true]
            ]
        ], JSON_PRETTY_PRINT);
    }
}
