<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class GoogleCloudStorageAdapter implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File\Storage\Adapters;

class GoogleCloudStorageAdapter extends S3Adapter
{
    /**
     * Implementation uses S3 Interoperability Mode
     * GCS supports the S3 XML API with HMAC keys perfectly.
     * This allows for reuse of the robust S3 logic without
     * implementing complex OAuth2 JWT signing for the JSON API.
     */
    public function __construct(array $config = [])
    {
        // Force GCS endpoint
        $config['endpoint'] = 'https://storage.googleapis.com';

        /**
         * GCS doesn't use standard regions in the same way for the endpoint,
         * but it's required for the SigV4 calculation.
         * 'auto' or 'us-east-1' usually works for the header.
         */

        $config['region'] = 'auto';

        parent::__construct($config);
    }
}
