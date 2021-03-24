<?php declare(strict_types=1);

namespace ThemeDevTools\Command;

use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class Find extends Command
{
    protected static $defaultName = 'tdt:find';
    const DESCRIPTION = 'Search theme plugin .twig, .scss and .js files for substring';
    const TWIG_DIR = 'views/storefront';
    const SCSS_DIR = 'app/storefront/src/scss';
    const JS_DIR = 'app/storefront/src';

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
        $this
            ->setDescription(self::DESCRIPTION)
            ->addArgument('filetype', InputArgument::OPTIONAL);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        #$input->getArgument('filetype');

        $io = new SymfonyStyle($input, $output);
        $io->title('SwThemeDevTools FIND: ' .  self::DESCRIPTION);

        $themes = $this->getThemeChoices();
        $theme_choices = array_column($themes, 'technical_name');
        $theme_choices['q'] = 'cancel';
        $target_theme_index = $io->choice('Select the theme to search', $theme_choices);
        if ($target_theme_index == 'q') {
            $io->text('Command cancelled');
            return self::SUCCESS;
        }
        $target_theme = $themes[$target_theme_index];
        $theme_path = $themes[$target_theme_index]['base_path'];

        $filetype_choices = [
            0 => 'scss',
            1 => 'twig',
            2 => 'js',
            'q' => 'cancel'
        ];
        $filetype_choice = $io->choice('Which filetypes do you want to search?', $filetype_choices);
        if ($filetype_choice == 'q') {
            $io->text('Command cancelled');
            return self::SUCCESS;
        }
        switch($filetype_choice) {
            case 0:
                $file_extension = 'scss';
                $search_dir = $theme_path . '/' . self::SCSS_DIR;
                break; 
            case 1:
                $file_extension = 'twig';
                $search_dir = $theme_path . '/' . self::TWIG_DIR;
                break;
            case 2:
                $file_extension = 'js';
                $search_dir = $theme_path . '/' . self::JS_DIR;
                break;      
        }
        $search = trim($io->ask('Enter search string or PCRE expression (Delimit with \'/\')'));
        if(substr($search, 0, 1) !== '/' || substr($search, -1) !== '/') {
            $search = '/' . preg_quote($search) . '/';
        }
        $finder = Finder::create()
            ->in($search_dir)
            ->name('*.' . $file_extension)
            ->files();

        foreach($finder as $file) {
            $clean_filepath = $file->getRealPath();
            if(!empty($result = $this->scanFile($clean_filepath, $search))) {
                $io->text('<fg=cyan>@ ' . str_replace($search_dir . '/', '', $clean_filepath) . '</>');
                foreach($result as $found) {
                    $io->text('<fg=green>' . $found['line_nr'] . ':</> ' . $found['line_content_colored']);    
                }
                $io->text('');
            }
        }
        return self::SUCCESS;
    }

    protected function scanFile(string $filepath, string $search) : array
    {
        $result = [];
        $line_counter = 1;
        $fh = fopen($filepath, 'r');
        if ($fh) {
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if(preg_match_all($search, $line, $matches)) {
                    $result[] = [
                        'line_nr' => $line_counter,
                        'line_content_colored' => $this->colorSubstr($line, $matches[0], 'yellow')
                    ];  
                } 
                $line_counter++;
            }
            fclose($fh);
        } else {
            // error opening the file.
        }
        return $result; 
    }

    protected function colorSubstr(string $string, array $substrings, string $color)
    {
        foreach($substrings as $substring) {
            $string = str_replace($substring, '<fg=' . $color . '>' . $substring . '</>', $string);
        }
        return $string;
    }

    protected function getThemeChoices(): array
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