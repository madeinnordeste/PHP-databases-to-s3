# PHP databases to S3

A simple PHP application to send MySQL dumps to Amazon S3

## Requirements

A `composer.json` file declaring the dependency on the AWS SDK is provided. To
install Composer and the SDK, run:

    curl -sS https://getcomposer.org/installer | php
    php composer.phar install



## Run

	php databases-to-s3.php