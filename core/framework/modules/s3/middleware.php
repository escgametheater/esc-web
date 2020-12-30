<?php
/**
 * AWS Middleware
 *
 * @package s3
 */
class AWSMiddleware extends Middleware
{
    public function process_request(Request $request)
    {
        // Create s3 object
        if ($request->config['aws'])
            $request->s3 = new S3($request->config, $request->settings()->getMediaDir());
        else
            $request->s3 = new S3Dummy();
    }
}
