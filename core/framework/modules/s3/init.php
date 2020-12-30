<?php
/**
 * AWS init.
 *
 */
const HTTP_OK = "200";


interface S3Uploader
{
    public function uploadFile($bucket, $key, $src, $content_type = null);

    public function upload_file_from_media_dir($src, $content_type = null);
}

class S3 implements S3Uploader
{
    const BUCKET_HOST_ASSETS = 'host-assets';
    const BUCKET_HOST_CONTROLLER_ASSETS = 'host-controller-assets';
    const BUCKET_SDK_ASSETS = 'sdk-assets';
    const BUCKET_GAME_ASSETS = 'game-assets';
    const BUCKET_GAME_CONTROLLER_ASSETS = 'game-controller-assets';
    const BUCKET_IMAGE_ASSETS = 'image-assets';
    const BUCKET_GAME_INSTANCE_LOGS = 'game-instance-logs';

    /** @var \Aws\S3\S3Client $s3 */
    protected $s3;

    /** @var  array $config */
    protected $config;
    protected $mediaDir;

    public function __construct($config, $mediaDir)
    {
        $this->config = $config[ESCConfiguration::AWS];
        $this->mediaDir = $mediaDir;

        $options = [
            'version' => 'latest',
            'credentials' => ['key' => $this->config['key'],
                'secret' => $this->config['secret_key'], ],
            'endpoint' => $this->config['endpoint'],
            'region' => $this->config['region'],
            'use_path_style_endpoint' => true,
        ];

        if (isset($config['aws']['http']['verify']))
            $options['http']['verify'] = $config['aws']['http']['verify'];

        $this->s3 = new Aws\S3\S3Client($options);
    }

    /**
     * @param $bucket
     * @return \Aws\Result
     */
    public function createBucket($bucket)
    {
        return $this->s3->createBucket([$bucket]);
    }

    /**
     * @return bool
     */
    public function testConnection()
    {
        try {
            $this->s3->listBuckets();
            return true;
        } catch (\Aws\Exception\AwsException $e) {
            return false;
        }
    }


    /**
     * @param $bucket
     * @param $key
     * @param $src
     * @param null $content_type
     * @throws Exception
     */
    public function uploadFile($bucket, $key, $src, $content_type = null)
    {
        if (is_file($src) == false) {
            throw new Exception('Missing source file');
        }

        if ($content_type == null) {
            $mime = finfo_open(FILEINFO_MIME);
            if ($mime == false) {
                throw new Exception('Unable to open finfo');
            }
            $content_type = finfo_file($mime, $src);
            finfo_close($mime);
        }

        $result = $this->s3->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'SourceFile' => $src,
                'ACL'    => 'public-read',
                'ContentType' => $content_type
        ]);

        if ($this->getStatusCode($result) != HTTP_OK)
            throw new Exception('s3 upload failed API failed for ' .
                                "{$bucket}/{$key} from {$src}");

    }

    /**
     * @param $bucket
     * @param $key
     * @param $blob
     * @param null $contentType
     * @throws Exception
     */
    public function uploadBlob($bucket, $key, $blob, $contentType = null)
    {
        if ($contentType == null) {
            $mime = finfo_open(FILEINFO_MIME);
            if ($mime == false) {
                throw new Exception('Unable to open finfo');
            }
            $contentType = finfo_buffer($mime, $blob);
            finfo_close($mime);
        }

        $result = $this->s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => $blob,
            'ACL'    => 'public-read',
            'ContentType' => $contentType
        ]);

        if ($this->getStatusCode($result) != HTTP_OK)
            throw new Exception('s3 upload failed API failed for ' .
                "{$bucket}/{$key} from blob");
    }

    /**
     * @param $src
     * @param null $content_type
     * @throws Exception
     */
    public function upload_file_from_media_dir($src, $content_type = null)
    {
        $parts = explode('/', str_replace($this->mediaDir, '', $src), 3);
        if (count($parts) != 3) {
            throw new Exception('S3 Upload: invalid media file '.$src);
        }
        $bucket = $parts[1];
        $key = $parts[2];
        $this->uploadFile($bucket, $key, $src);
    }

    /**
     * @param string $bucket
     * @param string $key
     * @param string $dest_path
     * @return \Aws\Result
     */
    public function download($bucket, $key, $dest_path)
    {
        return $this->s3->getObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'SaveAs' => $dest_path,
            'PathStyle' => true,
        ]);
    }

    /**
     * @param $bucket
     * @param $key
     * @return string|null
     */
    public function readIntoMemoryAsText($bucket, $key)
    {
        try {
            $result = $this->s3->getObject([
                'Bucket' => $bucket,
                'Key' => $key
            ]);

            $body = $result['Body']->__toString();

            return $body;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param $bucket
     * @param $key
     * @return string|null
     */
    public function readIntoMemory($bucket, $key)
    {
        try {
            $result = $this->s3->getObject([
                'Bucket' => $bucket,
                'Key' => $key
            ]);

            $body = $result['Body'];

            return $body;
        } catch (\Aws\Exception\AwsException $e) {
            throw $e;
        }
    }

    /**
     * getStatusCode returns HTTP status code of the given result.
     *
     * @param $result - AWS\S3 result object
     *
     * @return string - HTTP status code. E.g, "400" for Bad Request.
     */
    function getStatusCode($result) {
        return $result->toArray()['@metadata']['statusCode'];
    }

}

class S3Dummy implements S3Uploader
{
    public function uploadFile($bucket, $key, $src, $content_type = null)
    {
    }

    public function upload_file_from_media_dir($src, $content_type = null)
    {
    }

    public function __call($func, $args)
    {
    }
}

if (Modules::is_loaded(Modules::HTTP)) {

    require "middleware.php";
    Http::register_middleware(new AWSMiddleware());
}
