<?php declare(strict_types=1);

namespace ThemeDevTools\Command;

use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class Copy extends Command
{
    protected static $defaultName = 'tdt:copy';
    const DESCRIPTION = 'Transfer .twig and .scss files between theme plugins';
    const TWIG_DIR = 'views/storefront';
    const SCSS_DIR = 'app/storefront/src/scss';

    const SUCCESS = 0;
    const FAILURE = 1;

    public function __construct(StorefrontPluginRegistryInterface $pluginRegistry, Filesystem $filesystem)
    { 
        parent::__construct();
        $this->pluginRegistry = $pluginRegistry;
        $this->filesystem = $filesystem;
    }

    protected function configure()
    {
        $this->setDescription(self::DESCRIPTION);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('SwThemeDevTools COPY: ' .  self::DESCRIPTION);

        $themes = $this->getThemeChoices();
        $theme_choices = array_column($themes, 'technical_name');
        $theme_choices['q'] = 'cancel';
        $source_theme_index = $io->choice('Select the source theme', $theme_choices);
        if ($source_theme_index == 'q') {
            $io->text('Command cancelled');
            return self::SUCCESS;
        }
        $source_theme = $themes[$source_theme_index];
        $source_theme_path = $themes[$source_theme_index]['base_path'];

        $target_themes = array_values(array_filter(
            $themes,
            function($item) use ($source_theme) {
                return $item['technical_name'] !== $source_theme['technical_name'];
            }
        ));
        $filetype_choice = $io->choice(
            'Select filetype to copy', 
            [
                0 => 'Twig Template',
                1 => 'SCSS File',
                'q' => 'cancel'
            ]
        );
        if ($filetype_choice == 'q') {
            $io->text('Command cancelled');
            return self::SUCCESS;
        }
        if($filetype_choice == 0) {
            $search_dir = self::TWIG_DIR;
            $filetype = 'twig';
        } elseif ($filetype_choice == 1) {
            $search_dir = self::SCSS_DIR;
            $filetype = 'scss';  
        }
        $path_arr = [];
        while (true) {

            if(count($path_arr) > 0) {
                $io->text([
                    '',
                    '<fg=cyan>You are here: ' . join('/', $path_arr) . '</>',
                    ''
                ]);
            }
            $path = $source_theme_path . '/' . $search_dir . '/' . join('/', $path_arr);

            $finder = Finder::create()
                ->depth(0)
                ->in($path)
                ->filter(
                    function($item) use ($filetype) {
                        return $item->isDir() || $item->getExtension() == $filetype;
                    }
                )->sortByType();

            $dir_contents = [];
            foreach($finder as $file) {
                if ($file->isDir()) {
                    $display_text = '/' . $file->getBasename();
                } else {
                    $display_text = '<fg=yellow>' . $file->getBasename() . '</>';  
                }
                $dir_contents[] = [
                    'text' => $display_text,
                    'value' => $file->getBasename()
                ];
            }
            $choices = array_column($dir_contents, 'text');
            if(count($path_arr) > 0) {
                $choices['b'] = '<-- back';
            }
            $choices['q'] = 'cancel';
            $choice = $io->choice('Select file or descend into directory', $choices);
            if ($choice == 'b') {
                array_pop($path_arr);
                continue;
            }
            if ($choice == 'q') {
                $io->text('Command cancelled');
                return self::SUCCESS;
            }
            $path_arr[] = array_column($dir_contents, 'value')[$choice];
            $path = $source_theme_path . '/' . $search_dir . '/' . join('/', $path_arr);

            if($this->isDesiredFile($path, $filetype)) {
                $io->text([
                    '',
                    '<fg=cyan>Selected file: ' . join('/', $path_arr) . '</>',
                    ''
                ]);
                $theme_choices = array_column($target_themes, 'technical_name');
                $theme_choices['q'] = 'cancel';
                $target_theme_index = $io->choice('Select the target theme', $theme_choices);
                if ($target_theme_index == 'q') {
                    $io->text('Command cancelled');
                    return self::SUCCESS;
                }
                $target_theme = $target_themes[$target_theme_index];
                $target = $target_theme['base_path'] . '/' . $search_dir . '/' . join('/', $path_arr);
                if($this->filesystem->exists($target)) {

                    $confirm_overwrite = $io->confirm(
                        "File exists in {$target_theme['technical_name']}, overwrite?",
                        false
                    );
                    if(!$confirm_overwrite) {
                        $io->text('Command cancelled');
                        return self::SUCCESS;
                    }
                }
                try {
                    $this->filesystem->copy($path, $target, true);
                    $io->success(join('/', $path_arr) . " copied to {$target_theme['technical_name']}");
                } catch (\Exception $e) {
                    $io->error($e->getMessage()); 
                    return self::FAILURE;   
                }
                return self::SUCCESS;  
            }
        }
    }

    protected function isDesiredFile(string $path, string $type)
    {
        if($type === 'twig') {
            return preg_match('/\.html\.twig$/', $path);
        } elseif ($type === 'scss') {
            return preg_match('/\.scss$/', $path);   
        }
    }

    protected function getThemeChoices() : array
    {
        $choices = [];
        foreach ($this->pluginRegistry->getConfigurations()->getThemes() as $theme) {
            $choices[] = [
                'technical_name' => $theme->getTechnicalName(),
                'base_path' => $theme->getBasePath()
            ];
        }
        return $choices;
    }
}