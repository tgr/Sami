<?php

namespace Sami\Version;

use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Finder;

class DirectoryVersionCollection extends VersionCollection
{
    /** @var array $map version name => path */
    protected $map = array();
    protected $link;

    /**
     * @param string $link the source directory used by Sami (should not exist; will be a symlink to the right version directory)
     * @param array|string|null $map either the path to a diractory containing a subdirectory for each version,
     *   or an array mapping version names to paths, or an array mapping version names to (path, longname) pairs
     */
    public function __construct($link, $map = null)
    {
        $this->link = $link;
        if (is_array($map)) {
            foreach ($map as $name => $item) {
                if (is_array($item)) {
                    $this->addVersionPath($name, $item[0], $item[1]);
                } else {
                    $this->addVersionPath($name, $item);
                }
            }
        } else if (is_string($map)) {
            $this->mapDirectory($map);
        }
    }

    /**
     * Adds a version -> path mapping.
     * @param string $name version (short) name
     * @param string $path directory path
     */
    public function addVersionPath($name, $path, $longname = null)
    {
        $version = new Version($name, $longname);
        $this->add($version);
        $this->map[$name] = $path;
    }
    
    public function mapDirectory($path)
    {
        $finder = new Finder();
        $finder->directories()->in($path)->depth('== 0');
        $finder->sortByName();
        foreach ($finder as $dir) {
            $this->addVersionPath($dir->getRelativePath(), $dir->getRealpath());
        }
    }

    protected function switchVersion(Version $version)
    {
        $name = $version->getName();
        $this->switchLink($this->map[$name]);
    }

    protected function switchLink($target)
    {
        $builder = new ProcessBuilder(array('ln', '--symbolic', '--force', '--no-target-directory', $target, $this->link));
        $process = $builder->getProcess();
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Unable to run the command (%s).', $process->getErrorOutput()));
        }

        return $process->getOutput();
    }
}
