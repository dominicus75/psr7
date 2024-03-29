<?php declare(strict_types=1);

namespace Dominicus75\Psr7\Tests;

use Dominicus75\Psr7\{Stream, UploadedFile};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{UploadedFileInterface, StreamInterface};

/**
 * @covers Dominicus75\Psr7\UploadedFile
 * Based on Tobias Nyholm's work and Guzzle
 * @see https://github.com/Nyholm/psr7/blob/master/tests/UploadedFile.php
 * @see https://github.com/guzzle/psr7/blob/master/tests/UploadedFile.php
 */
class UploadedFileTest extends TestCase
{
    private static string $tmp_dir;
    private static string $upl_dir;
    private static string $tst_dir;
    private static string $files;
    private static array $throws;
    private static array $errors;
    private static $resource;
    private static Stream $stream;
    private static array $invalid_constructor_args;
    private static array $invalid_error_codes;
    private static array $constructor_exceptions;
    private array $mock_args;

    public static function setUpBeforeClass(): void
    {
        self::$tmp_dir = \sys_get_temp_dir().DIRECTORY_SEPARATOR;
        if (!\is_dir(self::$tmp_dir.DIRECTORY_SEPARATOR.'upload')) { 
            \mkdir(self::$tmp_dir.DIRECTORY_SEPARATOR.'upload'); 
        }
        self::$upl_dir = self::$tmp_dir.DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR;
        self::$tst_dir = __DIR__.DIRECTORY_SEPARATOR;
        self::$files   = self::$tst_dir.'files'.DIRECTORY_SEPARATOR;
        self::$throws  = [
            'type'    => \TypeError::class,
            'value'   => \ValueError::class,
            'runtime' => \RuntimeException::class,
            'invalid' => \InvalidArgumentException::class
        ];

        self::$errors = [
            \UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            \UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            \UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
            \UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            \UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            \UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            \UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];

        $string   = 'John Doe';
        $int      = 33;
        $float    = 88.5;
        $bool     = true;
        $null     = null;
        $array    = [
            'name'   => $string,
            'male'   => $bool,
            'email'  => $null,
            'age'    => $int,
            'weight' => $float
        ];
        $object   = (object) $array;
        self::$resource = \fopen('php://temp', 'rw+');
        self::$stream   = new Stream(resource: self::$resource);

        self::$invalid_constructor_args = [
            'file' => [
                'values' => [
                    $null,
                    $bool,
                    $int,
                    $float,
                    $array,
                    self::$resource,
                    $object
                ],
                'throws' => self::$throws['type']
            ],
            'error' => [
                'values' => [
                    $null,
                    $bool,
                    $string,
                    $float,
                    $array,
                    self::$resource,
                    $object
                ],
                'throws' => self::$throws['type']
            ],
            'size' => [
                'values' => [
                    $string,
                    $bool,
                    self::$stream,
                    $float,
                    $array,
                    self::$resource,
                    $object
                ],
                'throws' => self::$throws['type']
            ],
            'name' => [
                'values' => [
                    self::$stream,
                    $bool,
                    $int,
                    $float,
                    $array,
                    self::$resource,
                    $object
                ],
                'throws' => self::$throws['type']
            ]
        ];

        self::$invalid_error_codes    = [
            'values'  => [-10, 5, -1, 9, 10, 42, 123],
            'throws'  => self::$throws['value'],
            'message' => 'Error status for UploadedFile must be an UPLOAD_ERR_* constant'          
        ];

        self::$constructor_exceptions = [
            'file_is_not_uploaded' => [
                'values'  => \glob(self::$tst_dir.'*.*'),
                'throws'  => self::$throws['runtime'],
                'message' => 'It is not a valid uploaded file'
            ],
            'invalid_stream' => [
                'values'  => [
                    'php://stream',
                    'telnet://temp',
                    'file://memory'
                ],
                'throws'  => self::$throws['runtime'],
                'message' => 'Invalid filename. Unable to open the stream.'
            ],

        ];
    }

    protected function setUp(): void
    {
        foreach (\glob(self::$files.'*.*') as $file) {
            $filename = \basename($file);
            if (!\file_exists(self::$tmp_dir.$filename)) {
                \copy($file, self::$tmp_dir.$filename);
            }
        }

        $this->mock_args = [
            'uploaded_files' => [
                self::$tmp_dir.'lorem.txt',
                self::$tmp_dir.'random.csv',
                self::$tmp_dir.'tux.png'
            ],
            'uploaded_files_as_stream' => [
                new Stream(self::$tmp_dir.'lorem.txt', 'rt'),
                new Stream(self::$tmp_dir.'random.csv', 'rt'),
                new Stream(self::$tmp_dir.'tux.png', 'rt')
            ],
            'target_paths' => [
                self::$upl_dir.'new_lorem.txt',
                self::$upl_dir.'new_random.csv',
                self::$upl_dir.'new_tux.png'
            ],
            'mime_type' => [
                'text/plain',
                'text/csv',
                'image/png'
            ]
        ];
    }

    protected function tearDown(): void
    {
        foreach (\glob(self::$upl_dir.'*.*') as $file) {
            \unlink($file);
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (\glob(self::$upl_dir.'*.*') as $file) {
            \unlink($file);
        }
    }

    public function testInvalidFileArgThrowsExceptions()
    {
        foreach (self::$invalid_constructor_args['file']['values'] as $argument) {
            $this->expectException(self::$invalid_constructor_args['file']['throws']);
            new UploadedFile($argument, \UPLOAD_ERR_OK);
        }
    }

    public function testInvalidTypedErrorArgThrowsExceptions()
    {
        foreach (self::$invalid_constructor_args['error']['values'] as $argument) {
            $this->expectException(self::$invalid_constructor_args['error']['throws']);
            new UploadedFile(self::$stream, $argument);
        }
    }

    public function testInvalidErrorArgValueThrowsExceptions()
    {
        foreach (self::$invalid_error_codes['values'] as $argument) {
            $this->expectException(self::$invalid_error_codes['throws']);
            $this->expectExceptionMessage(self::$invalid_error_codes['message']);
            new UploadedFile(self::$stream, $argument);
        }
    }

    public function testUploadErrorThrowsExceptions()
    {
        foreach (self::$errors as $code => $message) {
            $this->expectException(self::$throws['runtime']);
            $this->expectExceptionMessage($message);
            new UploadedFile(self::$stream, $code);
        }
    }

    public function testInvalidSizeArgThrowsExceptions()
    {
        foreach (self::$invalid_constructor_args['size']['values'] as $argument) {
            $this->expectException(self::$invalid_constructor_args['size']['throws']);
            new UploadedFile(file: self::$stream, error: \UPLOAD_ERR_OK, size: $argument);
        }
    }

    public function testInvalidNameArgThrowsExceptions()
    {
        foreach (self::$invalid_constructor_args['name']['values'] as $argument) {
            $this->expectException(self::$invalid_constructor_args['name']['throws']);
            new UploadedFile(self::$stream, \UPLOAD_ERR_OK, $argument, self::$stream->getSize());
        }
    }

    public function testIfFileNotUploadedConstructorThrowsExceptions()
    {
        foreach (self::$constructor_exceptions['file_is_not_uploaded']['values'] as $argument) {
            $this->expectException(self::$constructor_exceptions['file_is_not_uploaded']['throws']);
            $this->expectExceptionMessage(self::$constructor_exceptions['file_is_not_uploaded']['message']);
            new UploadedFile($argument, \UPLOAD_ERR_OK);
        }
    }

    public function testIfSteamIsInvalidConstructorThrowsExceptions()
    {
        foreach (self::$constructor_exceptions['invalid_stream']['values'] as $argument) {
            $this->expectException(self::$constructor_exceptions['invalid_stream']['throws']);
            $this->expectExceptionMessage(self::$constructor_exceptions['invalid_stream']['message']);
            new UploadedFile($argument, \UPLOAD_ERR_OK);
        }
    }

    public function testMoveToMethodWithFile()
    {
        foreach ($this->mock_args['uploaded_files'] as $index => $file) {
            $mock = new MockUploadedFile($file, \UPLOAD_ERR_OK);
            $this->assertInstanceOf('Psr\Http\Message\UploadedFileInterface', $mock);
            $this->assertEquals($file, $mock->getClientFilename());
            $this->assertEquals($this->mock_args['mime_type'][$index], $mock->getClientMediaType());
            $mock->moveTo($this->mock_args['target_paths'][$index]);
            $this->assertFileExists($this->mock_args['target_paths'][$index]);
            $this->assertFileDoesNotExist($file);
        }
    }

    public function testMoveToMethodWithStream()
    {
        foreach ($this->mock_args['uploaded_files_as_stream'] as $index => $stream) {
            $tmp_file = $stream->getMetadata('uri');
            $mock     = new MockUploadedFile($stream, \UPLOAD_ERR_OK);
            $this->assertInstanceOf('Psr\Http\Message\UploadedFileInterface', $mock);
            $this->assertEquals($stream->getMetadata('uri'), $mock->getClientFilename());
            $this->assertEquals($this->mock_args['mime_type'][$index], $mock->getClientMediaType());
            $mock->moveTo($this->mock_args['target_paths'][$index]);
            $this->assertFileExists($this->mock_args['target_paths'][$index]);
            $this->assertFileDoesNotExist($tmp_file);
        }
    }

}