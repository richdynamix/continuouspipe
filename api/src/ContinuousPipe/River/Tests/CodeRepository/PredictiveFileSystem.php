<?php

namespace ContinuousPipe\River\Tests\CodeRepository;

use ContinuousPipe\River\CodeRepository\FileSystem\FileNotFound;
use ContinuousPipe\River\CodeRepository\FileSystem\RelativeFileSystem;

class PredictiveFileSystem implements RelativeFileSystem
{
    /**
     * @var array
     */
    private $files;

    /**
     * @var \ContinuousPipe\River\CodeRepository\FileSystem\RelativeFileSystem
     */
    private $decoratedFileSystem;

    /**
     * @param array              $files
     * @param \ContinuousPipe\River\CodeRepository\FileSystem\RelativeFileSystem $decoratedFileSystem
     */
    public function __construct(array $files, RelativeFileSystem $decoratedFileSystem = null)
    {
        $this->files = $files;
        $this->decoratedFileSystem = $decoratedFileSystem;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($filePath)
    {
        return array_key_exists($filePath, $this->files) || (
            null !== $this->decoratedFileSystem && $this->decoratedFileSystem->exists($filePath)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getContents($filePath)
    {
        if (!array_key_exists($filePath, $this->files)) {
            if (null !== $this->decoratedFileSystem) {
                return $this->decoratedFileSystem->getContents($filePath);
            }

            throw new FileNotFound(sprintf('File %s not found in predictive file system', $filePath));
        }

        return $this->files[$filePath];
    }
}
