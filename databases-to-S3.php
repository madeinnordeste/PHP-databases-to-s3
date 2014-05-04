<?php 

date_default_timezone_set('America/Maceio');
require 'vendor/autoload.php';
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\S3\S3Client;

/* CONFIGS */

// Amazon AWS
const KEY = 'YOU-AMAZON-AWS-KEY';
const SECRET = 'YOU-AMAZON-AWS-SECRET';
const BUCKET = 'YOU-BUCKET';
const SNS_TOPIC = FALSE;
//const SNS_TOPIC = 'arn:aws:sns:us-east-1:0998983989834:amazon-topic-name';
const REGION = 'us-east-1'; 
const SUBJECT = 'Subject to SNS notification';

// DATABASES
$databases = array(
					array('name' => 'database-1', 
						  'user' => 'user-database-1', 
						  'password' => 'password-databas-1'),
					array('name' => 'database-2', 
						  'user' => 'user-database-2', 
						  'password' => 'password-database-2'),
				);


function divisor(){
	print "\n";
	print str_repeat('- ', 30);
	print "\n";
}


// Dir to save dump
$dirs = array(dirname(__FILE__), 'dumps', date('Y-m'));
$path = implode('/', $dirs);
$output = shell_exec('mkdir -p '.$path);


$list = array('success' => array(), 'error' => array());

// Foreach databases
foreach ($databases as $db){
	
	divisor();
	
	print "Dump database: ".$db['name'];
	print "\n";	
	
	$file = $db['name'].'-'.date('Y-m-d--H-i-s').'.sql.gz';
	$file = implode('/', array($path, $file));
	$key  = implode('/', array(date('Y-m'), $db['name'], basename($file)));
	print "\tCreating file: \n";
	print "\t".$file."\n";
	
	//mysql dump
	$command ='mysqldump -h localhost ';
	$command.='-u '.$db['user'].' ';
	$command.='-p'.$db['password'].' '.$db['name'].' ';
	$command.='| gzip -9  > ';
	$command.=$file;

	$output = shell_exec($command);

	print "\tDone.\n\n";


	//send to amazon
	print "Send to Amazon S3\n";
	print "\tBucket: ".BUCKET."\n";
	print "\tKey: ".$key."\n";

					
	// Instantiate the client.
	$s3 = S3Client::factory(array(
    	'key'    => KEY,
    	'secret' => SECRET
	));

	// Prepare the upload parameters.
	$uploader = UploadBuilder::newInstance()
		->setClient($s3)
	    ->setSource($file)
	    ->setBucket(BUCKET)
	    ->setKey($key)
	    ->setMinPartSize(25 * 1024 * 1024)//optional
	    ->setConcurrency(4)//optional
	    ->build();

	// Perform the upload. Abort the upload if something goes wrong.
	try {
	    $uploader->upload();
	    print "\tUpload complete.\n";

	    //remove local database file
	    @unlink($file);

		$list['success'][] = basename($file);

	} catch (MultipartUploadException $e) {
	    $uploader->abort();
	    print "\tUpload failed.\n";
	    
	    $list['error'][] = basename($file).' : '.$e->getMessage();
	}
	
	
}


// SNS notifications
if(SNS_TOPIC){

	$message = '';

	if( sizeof($list['success']) ){
		$message.="Success:\n\n";
		$message.=implode("\n\n", $list['success']);
		$message."\n\n\n\n";
	}

	if( sizeof($list['error']) ){
		$message.="Error:\n";
		$message.=implode("\n", $list['error']);
		$message."\n\n\n\n";
	}

	$sns = Aws\Sns\SnsClient::factory(array(
    	'key'    => KEY,
    	'secret' => SECRET,
    	'region' => REGION,
	));

	$sns->publish(array(
    	'TopicArn' => SNS_TOPIC,
    	'Message' => $message,
    	'Subject' => SUBJECT,
	));
}



echo "\n";
