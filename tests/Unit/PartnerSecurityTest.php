<?php

namespace Tests\Unit;

use App\Services\Partners\IpRange;
use App\Services\Partners\RequestSigner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PartnerSecurityTest extends TestCase
{
    public function test_the_signature_covers_every_part_of_the_request(): void
    {
        $signer = new RequestSigner;
        $base = $signer->sign('sk_secret', 'PUT', '/api/partner/v1/offers', '1700000000', 'nonce-0123456789ab', '{"a":1}');

        $this->assertSame($base, $signer->sign('sk_secret', 'put', '/api/partner/v1/offers', '1700000000', 'nonce-0123456789ab', '{"a":1}'));
        $this->assertNotSame($base, $signer->sign('sk_other', 'PUT', '/api/partner/v1/offers', '1700000000', 'nonce-0123456789ab', '{"a":1}'));
        $this->assertNotSame($base, $signer->sign('sk_secret', 'GET', '/api/partner/v1/offers', '1700000000', 'nonce-0123456789ab', '{"a":1}'));
        $this->assertNotSame($base, $signer->sign('sk_secret', 'PUT', '/api/partner/v1/offers?x=1', '1700000000', 'nonce-0123456789ab', '{"a":1}'));
        $this->assertNotSame($base, $signer->sign('sk_secret', 'PUT', '/api/partner/v1/offers', '1700000001', 'nonce-0123456789ab', '{"a":1}'));
        $this->assertNotSame($base, $signer->sign('sk_secret', 'PUT', '/api/partner/v1/offers', '1700000000', 'nonce-0123456789ac', '{"a":1}'));
        $this->assertNotSame($base, $signer->sign('sk_secret', 'PUT', '/api/partner/v1/offers', '1700000000', 'nonce-0123456789ab', '{"a":2}'));

        $this->assertTrue($signer->verify('sk_secret', strtoupper($base), 'PUT', '/api/partner/v1/offers', '1700000000', 'nonce-0123456789ab', '{"a":1}'));
    }

    public function test_ip_ranges_are_validated(): void
    {
        foreach (['203.0.113.10', '203.0.113.0/24', '2001:db8::1', '2001:db8::/48', '10.0.0.0/16'] as $ok) {
            $this->assertTrue(IpRange::isValid($ok), $ok);
        }
        foreach (['', 'localhost', '300.1.1.1', '203.0.113.0/33', '2001:db8::/129', '1.2.3.4/abc'] as $bad) {
            $this->assertFalse(IpRange::isValid($bad), $bad);
        }
    }

    public function test_internet_wide_ranges_are_refused(): void
    {
        $this->assertTrue(IpRange::isTooBroad('0.0.0.0/0'));
        $this->assertTrue(IpRange::isTooBroad('10.0.0.0/8'));
        $this->assertFalse(IpRange::isTooBroad('203.0.113.0/24'));

        $this->expectException(InvalidArgumentException::class);
        IpRange::normalise(['203.0.113.10', '0.0.0.0/0']);
    }
}
