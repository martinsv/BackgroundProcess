<?php
namespace Kohkimakimoto\BackgroundProcess;

use Symfony\Component\Filesystem\Filesystem;

/**
 * BackgroundProcess
 * @author Kohki Makimoto
 */
class BackgroundProcess
{
    protected $process;
    protected $key;

    protected $workingDirectory;

    protected $jsonPath;

    protected $processPHPPath;

    protected $filePrefix;

    /**
     * Constractor.
     *
     * @param string $commandline Commandline to run
     * @param array $options Options to set environment.
     */
    public function __construct($commandline, $options = array())
    {
      $this->commandline = $commandline;
      $this->options = $options;

      // Set up key prefix
      if (isset($options['key_prefix'])) {
        $this->keyPrefix = $options['key_prefix'];
      } else {
        // default value
        $this->keyPrefix = "process.";
      }

      // Set up key
      $this->key = $this->generateKey();

      // Set up workingDirectory
      if (isset($options['working_directory'])) {
        $this->workingDirectory = $options['working_directory'];
      } else {
        // default value
        $this->workingDirectory = "/tmp/php/background_process";
      }
    }

    /**
     * Run the process.
     */
    public function run()
    {
      $this->writeExecutablePHPFile();
      $command = $this->getBackgroundProcessingRunCommand();
      exec($command);
    }

    /**
     * Write Executable PHP file to run the process in background.
     * @throws Exception
     */
    public function writeExecutablePHPFile()
    {
      $fs = new Filesystem();
      $path = $this->getExecutablePHPFilePath();

      $currentUmask = umask();
      umask(0000);

      if (!$fs->exists(dirname($path))) {
        $fs->mkdir(dirname($path), 0777);
      }

      if ($fs->exists($path)) {
        throw new Exception("Executable PHP file $path is already exists.");
      }

      if (!$fp = @fopen($path, 'wb')) {
        throw new Exception("Unable to write to $path.");
      }

      $commandline = $this->getCommandline();
      $key = $this->getKey();

      $contents =<<<EOF
<?php
//
// This file was generated by Kohkimakimoto/BackgroundProcess automatically.
//
\$key = "$key";
\$pid = posix_getpid();
\$meta = json_encode(array(
    "key" => \$key,
    "pid" => \$pid
));
\$metaPath = __DIR__."/${key}.json";

// Put meta file to save pid.
file_put_contents(\$metaPath, \$meta);

exec("$commandline");

// Delete meta file and self;
unlink(\$metaPath);
unlink(__FILE__);

EOF;

      @fwrite($fp, $contents);
      @fclose($fp);

      umask($currentUmask);

      $this->processPHPPath = $path;
      return $this->processPHPPath;
    }

    public function getExecutablePHPFilePath()
    {
      $dir = rtrim($this->getWorkingDirectory(), "/");
      $key = $this->getKey();

      return $dir."/".$key.".php";
    }

    /**
     * Generate unique key for indentifing background process.
     */
    public function generateKey()
    {
      return $this->getKeyPrefix().uniqid(getmypid());
    }

    /**
     * Get key
     */
    public function getKey()
    {
      return $this->key;
    }

    /**
     * Set key
     * @param unknown $key
     */
    public function setKey($key)
    {
      $this->key = $key;
    }

    /**
     * Set working directory.
     * @param unknown $workingDirectory
     */
    public function setWorkingDirectory($workingDirectory)
    {
      $this->workingDirectory = $workingDirectory;
    }

    /**
     * Get working directory.
     */
    public function getWorkingDirectory()
    {
      return $this->workingDirectory;
    }

    /**
     * Set file prefix
     * @param unknown $filePrefix
     */
    public function setKeyPrefix($keyPrefix)
    {
      $this->keyPrefix = $keyPrefix;
    }

    /**
     * Get working directory.
     */
    public function getKeyPrefix()
    {
      return $this->keyPrefix;
    }

    /**
     * Get commandline.
     */
    public function getCommandline()
    {
      return $this->commandline;
    }

    /**
     * set commandline.
     */
    public function setCommandline($commandline)
    {
      $this->commandline = $commandline;
    }

    public function getBackgroundProcessingRunCommand()
    {
      return sprintf('nohup php %s > /dev/null 2>&1 < /dev/null &', $this->processPHPPath);
    }

}

