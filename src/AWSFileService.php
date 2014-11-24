<?php
/**
 * Created by PhpStorm.
 * User: onysko
 * Date: 17.11.2014
 * Time: 14:12
 */
namespace samson\fs;

use Aws\S3\S3Client;
use Aws\Common\Credentials\Credentials;

/**
 * Amazon Web Services Adapter implementation
 * @package samson\upload
 */
class AWSFileService extends \samson\core\CompressableService implements IFileSystem
{
    /** @var string Identifier */
    protected $id = 'fs_aws';

    /** @var S3Client $client Aws services user */
    protected $client;

    /** @var \samson\fs\LocalFileService Pointer to local file service  */
    protected $localFS;

    /** @var string $bucket Aws bucket name */
    public $bucket;

    /** @var string $accessKey */
    public $accessKey;

    /** @var string $secretKey */
    public $secretKey;

    /** @var string $bucketURL Url of amazon bucket */
    public $bucketURL;

    /**
     * Adapter initialization
     * @param array $params
     * @return bool
     */
    public function init(array $params = array())
    {
        // Get client object instance from input parameters
        $this->client = & $params['client'];
        // No client is passed
        if (!isset($params['client'])) {
            // Use S3 clients, create authorization object and instantiate the S3 client with AWS credentials
            $this->client = S3Client::factory(array(
                'credentials' => new Credentials($this->accessKey, $this->secretKey)
            ));
        }

        // Set pointer to local file system service
        $this->localFS = & m('fs_local');

        // Call parent initialization
        return parent::init($params);
    }

    /**
     * Write data to a specific relative location
     *
     * @param mixed $data Data to be written
     * @param string $filename File name
     * @param string $uploadDir Relative file path
     * @return string|boolean Relative path to created file, false if there were errors
     */
    public function write($data, $filename = '', $uploadDir = '')
    {
        // Upload data to Amazon S3
        $this->client->putObject(array(
            'Bucket'       => $this->bucket,
            'Key'          => $uploadDir.'/'.$filename,
            'Body'         => $data,
            'CacheControl' => 'max-age=1296000',
            'ACL'          => 'public-read'
        ));

        // Build absolute path to uploaded resource
        return $this->bucketURL.'/'.$uploadDir.'/';
    }

    /**
     * Check existing current file in current file system
     * @param $filename string Filename
     * @return boolean File exists or not
     */
    public function exists($filename)
    {
        // TODO: Change to curl - as this method fetches all responce!
        
        return file_get_contents($filename);
    }

    /**
     * Read the file from current file system
     * @param $filePath string Path to file
     * @param $filename string
     * @return mixed
     */
    public function read($filePath, $filename = null)
    {
        // TODO: Why this if we have - S3Client::getObject()?

        // Create temporary catalog
        if (!is_dir('temp')) {
            mkdir('temp', 0775);
        }

        // Create file in local file system
        $this->localFS->write(file_get_contents($filePath), $filename, 'temp');

        return 'temp/'.$filename;
    }

    /**
     * Write a file to selected location
     * @param $filePath string Path to file
     * @param $filename string
     * @param $uploadDir string
     * @return mixed
     */
    public function move($filePath, $filename, $uploadDir)
    {
        // TODO: What difference with current write()?

        // Upload file to Amazon S3
        $this->client->putObject(array(
            'Bucket'       => $this->bucket,
            'Key'          => $uploadDir.'/'.$filename,
            'SourceFile'   => $filePath,
            'CacheControl' => 'max-age=1296000',
            'ACL'          => 'public-read'
        ));
    }

    /**
     * Delete file from current file system
     * @param $filePath string File for deleting
     * @return mixed
     */
    public function delete($filePath)
    {
        $this->client->deleteObject(array(
            'Bucket' => $this->bucket,
            'Key'    => str_replace($this->bucketURL, '', $filePath)
        ));
    }

    /**
     * Get file extension in current file system
     * @param $filePath string Path
     * @return string|bool false if extension not found, otherwise file extension
     */
    public function extension($filePath)
    {
        // Get last three symbols of file path
        $extension = substr($filePath, -3);

        // Fix jpeg extension
        if ($extension == 'peg') {
            $extension = 'jpeg';
        }

        return $extension;
    }
}