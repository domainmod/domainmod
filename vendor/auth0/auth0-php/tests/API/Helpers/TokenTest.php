<?php
namespace Auth0\Tests\Api\Helpers;

use Auth0\SDK\API\Helpers\TokenGenerator;
use Auth0\SDK\JWTVerifier;
use Auth0\SDK\Auth0JWT;
use Auth0\SDK\Exception\CoreException;
use Auth0\SDK\Exception\InvalidTokenException;
use Auth0\Tests\Traits\ErrorHelpers;
use Auth0\SDK\Helpers\JWKFetcher;
use Firebase\JWT\JWT;

/**
 * Class TokenTest
 *
 * @package Auth0\Tests\Api\Helpers
 */
class TokenTest extends \PHPUnit_Framework_TestCase
{

    use ErrorHelpers;

    /**
     * Default Client ID.
     */
    const CLIENT_ID = '__test_client_id__';

    /**
     * Default Client Secret.
     */
    const CLIENT_SECRET = '__test_client_secret__';

    /**
     * Test legacy misspelling.
     *
     * @return void
     */
    public function testThatSuportedAlgsThrowsException()
    {
        $caught_exception = false;
        try {
            new JWTVerifier( [ 'suported_algs' => uniqid() ] );
        } catch (CoreException $e) {
            $caught_exception = $this->errorHasString( $e, '`suported_algs` was properly renamed to `supported_algs`' );
        }

        $this->assertTrue($caught_exception);
    }

    /**
     * Test for audience config param.
     *
     * @return void
     */
    public function testThatMissingAudThrowsException()
    {
        $caught_exception = false;
        try {
            new JWTVerifier([]);
        } catch (CoreException $e) {
            $caught_exception = $this->errorHasString( $e, 'The audience is mandatory' );
        }

        $this->assertTrue($caught_exception);
    }

    /**
     * Test unsupported algorithms in the config param.
     *
     * @return void
     */
    public function testThatOtherAlgsThrowsException()
    {
        $caught_exception = false;
        try {
            new JWTVerifier( [
                'valid_audiences' => [ uniqid() ],
                'supported_algs' => [ 'RS256', 'RS512' ],
            ] );
        } catch (CoreException $e) {
            $caught_exception = $this->errorHasString( $e, 'Cannot support the following algorithm(s): RS512' );
        }

        $this->assertTrue($caught_exception);
    }

    /**
     * Test for missing issuer for RS256 tokens.
     *
     * @return void
     */
    public function testThatMissingIssThrowsException()
    {
        $caught_exception = false;
        try {
            new JWTVerifier( [
                'valid_audiences' => [ uniqid() ],
                'supported_algs' => [ 'RS256' ],
            ] );
        } catch (CoreException $e) {
            $caught_exception = $this->errorHasString( $e, 'The token iss property is required' );
        }

        $this->assertTrue($caught_exception);
    }

    /**
     * Test that a secret is required for an HS256 token.
     *
     * @return void
     */
    public function testThatMissingSecretThrowsException()
    {
        $caught_exception = false;
        try {
            new JWTVerifier( [
                'valid_audiences' => [ uniqid() ],
                'supported_algs' => [ 'HS256' ],
            ] );
        } catch (CoreException $e) {
            $caught_exception = $this->errorHasString($e, 'The client_secret is required');
        }

        $this->assertTrue($caught_exception);
    }

    /**
     * Test that a malformed token is rejected.
     *
     * @return void
     *
     * @throws CoreException See Auth0\SDK\JWTVerifier::verifyAndDecode().
     */
    public function testThatTokenWithIncorrectSegmentsThrowsException()
    {
        $verifier = new JWTVerifier( [
            'valid_audiences' => [ uniqid() ],
            'supported_algs' => [ 'HS256' ],
            'client_secret' => self::CLIENT_SECRET,
        ] );

        $caught_exception = false;
        try {
            $verifier->verifyAndDecode( uniqid() );
        } catch (InvalidTokenException $e) {
            $caught_exception = $this->errorHasString($e, 'Wrong number of segments');
        }

        $this->assertTrue($caught_exception);

        $caught_exception = false;
        try {
            $verifier->verifyAndDecode( uniqid().'.'.uniqid() );
        } catch (InvalidTokenException $e) {
            $caught_exception = $this->errorHasString($e, 'Wrong number of segments');
        }

        $this->assertTrue($caught_exception);
    }

    /**
     * Test that a malformed token or missing algorithm fails.
     *
     * @return void
     *
     * @throws CoreException See Auth0\SDK\JWTVerifier::verifyAndDecode().
     */
    public function testThatTokenWithBadAlgThrowsException()
    {
        $dummy_segment = JWT::urlsafeB64Encode('{"dummy":"yes"}');
        $verifier      = new JWTVerifier( [
            'valid_audiences' => [ uniqid() ],
            'supported_algs' => [ 'HS256' ],
            'client_secret' => self::CLIENT_SECRET,
        ] );

        // 1. A token with a head that cannot be JSON decoded should throw an exception.
        $caught_exception = false;
        $error_msg        = 'No exception caught';
        try {
            $verifier->verifyAndDecode( uniqid().'.'.uniqid().'.'.uniqid() );
        } catch (InvalidTokenException $e) {
            $error_msg        = $e->getMessage();
            $caught_exception = $this->errorHasString($e, 'Malformed token');
        }

        $this->assertTrue($caught_exception, $error_msg);

        // 2. A token with a payload that cannot be JSON decoded should throw an exception.
        $caught_exception = false;
        $error_msg        = 'No exception caught';
        try {
            $verifier->verifyAndDecode( $dummy_segment.'.'.uniqid().'.'.uniqid() );
        } catch (InvalidTokenException $e) {
            $error_msg        = $e->getMessage();
            $caught_exception = $this->errorHasString($e, 'Malformed token');
        }

        $this->assertTrue($caught_exception, $error_msg);

        // 3. A token without an alg property should throw an exception.
        $head_obj = (object) [ 'typ' => 'JWT' ];
        $jwt_head = JWT::urlsafeB64Encode(JWT::jsonEncode($head_obj));

        $caught_exception = false;
        $error_msg        = 'No exception caught';
        try {
            $verifier->verifyAndDecode( $jwt_head.'.'.$dummy_segment.'.'.uniqid() );
        } catch (InvalidTokenException $e) {
            $error_msg        = $e->getMessage();
            $caught_exception = $this->errorHasString($e, 'Token algorithm not found');
        }

        $this->assertTrue($caught_exception, $error_msg);

        // 4. A token with an alg not in supported_algs should throw an exception.
        $head_obj->alg = 'RS256';
        $jwt_head      = JWT::urlsafeB64Encode(JWT::jsonEncode($head_obj));

        $caught_exception = false;
        $error_msg        = 'No exception caught';
        try {
            $verifier->verifyAndDecode( $jwt_head.'.'.$dummy_segment.'.'.uniqid() );
        } catch (InvalidTokenException $e) {
            $error_msg        = $e->getMessage();
            $caught_exception = $this->errorHasString($e, 'Token algorithm not supported');
        }

        $this->assertTrue($caught_exception, $error_msg);
    }

    /**
     * Test that an invalid audience is rejected.
     *
     * @return void
     *
     * @throws CoreException See Auth0\SDK\JWTVerifier::verifyAndDecode().
     */
    public function testThatTokenWithInvalidAudThrowsException()
    {
        $verifier = new JWTVerifier( [
            'valid_audiences' => [ '__valid_aud__' ],
            'supported_algs' => [ 'RS256' ],
            'authorized_iss' => [ '__valid_iss__' ],
        ] );

        // 1. A token with an invalid audience should throw an exception.
        $head_obj      = new \stdClass();
        $head_obj->typ = 'JWT';
        $head_obj->alg = 'RS256';
        $jwt_head      = JWT::urlsafeB64Encode(JWT::jsonEncode($head_obj));

        $payload_obj      = new \stdClass();
        $payload_obj->aud = ['__invalid_aud__'];
        $jwt_payload      = JWT::urlsafeB64Encode(JWT::jsonEncode($payload_obj));

        $caught_exception = false;
        $error_msg        = 'No exception caught';
        try {
            $verifier->verifyAndDecode( $jwt_head.'.'.$jwt_payload.'.'.uniqid() );
        } catch (InvalidTokenException $e) {
            $error_msg        = $e->getMessage();
            $caught_exception = $this->errorHasString(
                $e,
                'Invalid token audience __invalid_aud__; expected __valid_aud__'
            );
        }

        $this->assertTrue($caught_exception, $error_msg);

        // 2. A token without a key ID should throw an exception.
        $payload_obj->aud = '__valid_aud__';
        $jwt_payload      = JWT::urlsafeB64Encode(JWT::jsonEncode($payload_obj));

        $caught_exception = false;
        $error_msg        = 'No exception caught';
        try {
            $verifier->verifyAndDecode( $jwt_head.'.'.$jwt_payload.'.'.uniqid() );
        } catch (CoreException $e) {
            $error_msg        = $e->getMessage();
            $caught_exception = $this->errorHasString($e, 'Token key ID is missing for RS256 token');
        }

        $this->assertTrue($caught_exception, $error_msg);

        // 3. A token with an invalid issuer should throw an exception.
        $head_obj->kid = uniqid();
        $jwt_head      = JWT::urlsafeB64Encode(JWT::jsonEncode($head_obj));

        $payload_obj->iss = '__invalid_iss__';
        $jwt_payload      = JWT::urlsafeB64Encode(JWT::jsonEncode($payload_obj));

        $caught_exception = false;
        $error_msg        = 'No exception caught';
        try {
            $verifier->verifyAndDecode( $jwt_head.'.'.$jwt_payload.'.'.uniqid() );
        } catch (CoreException $e) {
            $error_msg        = $e->getMessage();
            $caught_exception = $this->errorHasString($e, 'We cannot trust on a token issued by');
        }

        $this->assertTrue($caught_exception, $error_msg);

        // 4. A token with an invalid signature should throw an exception.
        $verifier = new JWTVerifier( [
            'valid_audiences' => [ '__valid_aud__' ],
            'client_secret' => self::CLIENT_SECRET,
        ] );

        $head_obj->alg = 'HS256';
        $jwt_head      = JWT::urlsafeB64Encode(JWT::jsonEncode($head_obj));

        $payload_obj->iss = '__valid_iss__';
        $jwt_payload      = JWT::urlsafeB64Encode(JWT::jsonEncode($payload_obj));

        $caught_exception = false;
        $error_msg        = 'No exception caught';
        try {
            $verifier->verifyAndDecode( $jwt_head.'.'.$jwt_payload.'.'.JWT::urlsafeB64Encode(uniqid()) );
        } catch (CoreException $e) {
            $error_msg        = $e->getMessage();
            $caught_exception = $this->errorHasString($e, 'Signature verification failed');
        }

        $this->assertTrue($caught_exception, $error_msg);
    }

    /**
     * Test a successful HS256 token decoding.
     *
     * @return void
     *
     * @throws CoreException See Auth0\SDK\JWTVerifier::verifyAndDecode().
     * @throws InvalidTokenException See Auth0\SDK\JWTVerifier::verifyAndDecode().
     */
    public function testSuccessfulHs256TokenDecoding()
    {
        $token_generator = new TokenGenerator( self::CLIENT_ID, self::CLIENT_SECRET );

        // 1. Test that an encoded client secret can be used.
        $verifier = new JWTVerifier( [
            'valid_audiences' => [ self::CLIENT_ID ],
            'client_secret' => self::CLIENT_SECRET,
        ] );
        $jwt      = $token_generator->generate( ['users' => ['actions' => ['read']]] );
        $decoded  = $verifier->verifyAndDecode($jwt);

        $this->assertObjectHasAttribute('aud', $decoded);
        $this->assertEquals(self::CLIENT_ID, $decoded->aud);
        $this->assertObjectHasAttribute('scopes', $decoded);
        $this->assertObjectHasAttribute('users', $decoded->scopes);
        $this->assertObjectHasAttribute('actions', $decoded->scopes->users);
        $this->assertArraySubset(['read'], $decoded->scopes->users->actions);

        // 2. Test that a non-encoded client secret can be used.
        $verifier = new JWTVerifier( [
            'valid_audiences' => [ self::CLIENT_ID ],
            'client_secret' => self::CLIENT_SECRET,
            'secret_base64_encoded' => false,
        ] );
        $jwt      = $token_generator->generate(
            ['users' => ['actions' => ['read']]],
            TokenGenerator::DEFAULT_LIFETIME,
            false
        );
        $decoded  = $verifier->verifyAndDecode($jwt);

        $this->assertObjectHasAttribute('aud', $decoded);
        $this->assertEquals(self::CLIENT_ID, $decoded->aud);
        $this->assertObjectHasAttribute('scopes', $decoded);
        $this->assertObjectHasAttribute('users', $decoded->scopes);
        $this->assertObjectHasAttribute('actions', $decoded->scopes->users);
        $this->assertArraySubset(['read'], $decoded->scopes->users->actions);
    }

    /**
     * Test a successful RS256 token decoding.
     *
     * @return void
     *
     * @throws \Exception See Auth0\SDK\JWTVerifier::verifyAndDecode().
     */
    public function testSuccessfulRs256TokenDecoding()
    {
        // Mock the JWKFetcher object.
        $mocked_jwks = $this
            ->getMockBuilder( JWKFetcher::class )
            ->setMethods( [ 'requestJwkX5c' ] )
            ->getMock();
        $mocked_jwks->method( 'requestJwkX5c' )->willReturn( uniqid() );

        // Mock the JWT object.
        $expected_sub  = uniqid();
        $verifier_args = [
            'valid_audiences' => [ self::CLIENT_ID ],
            'client_secret' => self::CLIENT_SECRET,
            'supported_algs' => [ 'RS256' ],
            'authorized_iss' => [ '__valid_iss__' ],
            'jwks_path' => 'path/to/custom/jwks.json',
        ];
        $mocked_jwt    = $this
            ->getMockBuilder( JWTVerifier::class )
            ->setConstructorArgs( [ $verifier_args, $mocked_jwks ] )
            ->setMethods( [ 'decodeToken' ] )
            ->getMock();
        $mocked_jwt->method( 'decodeToken' )->willReturn( (object) ['sub' => $expected_sub] );

        $head_obj      = new \stdClass();
        $head_obj->typ = 'JWT';
        $head_obj->alg = 'RS256';
        $head_obj->kid = uniqid();
        $jwt_head      = JWT::urlsafeB64Encode(JWT::jsonEncode($head_obj));

        $payload_obj      = new \stdClass();
        $payload_obj->aud = self::CLIENT_ID;
        $payload_obj->iss = '__valid_iss__';
        $jwt_payload      = JWT::urlsafeB64Encode(JWT::jsonEncode($payload_obj));

        $jwt     = $jwt_head.'.'.$jwt_payload.'.'.uniqid();
        $decoded = $mocked_jwt->verifyAndDecode($jwt);

        $this->assertObjectHasAttribute('sub', $decoded);
        $this->assertEquals($expected_sub, $decoded->sub);
    }

    /**
     * Test the deprecated Auth0JWT::decode() method.
     *
     * @return void
     */
    public function testDeprecatedTestTokenGenerationDecode()
    {
        $token_generator = new TokenGenerator( self::CLIENT_ID, self::CLIENT_SECRET );
        $jwt             = $token_generator->generate(['users' => ['actions' => ['read']]]);
        $decoded         = Auth0JWT::decode($jwt, self::CLIENT_ID, self::CLIENT_SECRET);
        $this->assertObjectHasAttribute('aud', $decoded);
        $this->assertEquals(self::CLIENT_ID, $decoded->aud);
        $this->assertObjectHasAttribute('scopes', $decoded);
        $this->assertObjectHasAttribute('users', $decoded->scopes);
        $this->assertObjectHasAttribute('actions', $decoded->scopes->users);
        $this->assertArraySubset(['read'], $decoded->scopes->users->actions);
    }
}
