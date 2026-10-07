<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Services\AI\DTOs\SellerEmailProposal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SellerEmailProposalTest extends TestCase
{
    public function test_valid_proposal_creates_instance_and_computes_deterministic_fingerprint(): void
    {
        $proposal = new SellerEmailProposal(
            sellerId: 10,
            recipientEmail: 'seller@example.com',
            subject: 'Order Ready',
            message: 'Your order #101 is ready for shipment.',
            sellerName: 'Rahim',
        );

        $this->assertSame(10, $proposal->sellerId);
        $this->assertSame('seller@example.com', $proposal->recipientEmail);
        $this->assertSame('Order Ready', $proposal->subject);
        $this->assertSame('Your order #101 is ready for shipment.', $proposal->message);
        $this->assertSame('Rahim', $proposal->sellerName);

        $fingerprint = $proposal->generateFingerprint(workspaceId: 1);
        $this->assertNotEmpty($fingerprint);

        $proposalWithFp = $proposal->withFingerprint(workspaceId: 1);
        $this->assertSame($fingerprint, $proposalWithFp->fingerprint);

        $array = $proposalWithFp->toArray();
        $this->assertSame(10, $array['seller_id']);
        $this->assertSame('seller@example.com', $array['recipient_email']);
        $this->assertSame($fingerprint, $array['fingerprint']);

        $restored = SellerEmailProposal::fromArray($array);
        $this->assertSame(10, $restored->sellerId);
        $this->assertSame('seller@example.com', $restored->recipientEmail);
        $this->assertSame($fingerprint, $restored->fingerprint);
    }

    public function test_invalid_email_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('recipientEmail must be a valid email address');

        new SellerEmailProposal(
            sellerId: 1,
            recipientEmail: 'not-an-email',
            subject: 'Subject',
            message: 'Body',
        );
    }

    public function test_empty_message_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('message cannot be empty');

        new SellerEmailProposal(
            sellerId: 1,
            recipientEmail: 'seller@example.com',
            subject: 'Subject',
            message: '   ',
        );
    }

    public function test_empty_subject_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('subject cannot be empty');

        new SellerEmailProposal(
            sellerId: 1,
            recipientEmail: 'seller@example.com',
            subject: '   ',
            message: 'Body',
        );
    }

    public function test_invalid_seller_id_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sellerId must be a positive integer');

        new SellerEmailProposal(
            sellerId: 0,
            recipientEmail: 'seller@example.com',
            subject: 'Subject',
            message: 'Body',
        );
    }
}
