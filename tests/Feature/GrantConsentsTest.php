<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use DomainException;

use Amarenkov\MutableContentAi\Models\GrantConsent;
use Amarenkov\MutableContentAi\Models\Settings;
use Amarenkov\MutableContentAi\Services\Access;
use Amarenkov\MutableContentAi\Services\Consents;
use Amarenkov\MutableContentAi\Services\Grants;
use Amarenkov\MutableContentAi\Services\UserSettings;

use Amarenkov\MutableContentAi\Tests\Fixtures\Models\User;
use Amarenkov\MutableContentAi\Tests\TestCase;

class GrantConsentsTest extends TestCase
{
    // protected
    protected User $friend;

    protected User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->friend = User::query()->create(['email' => 'friend@example.com', 'name' => 'Friend', 'password' => 'secret']);
        $this->stranger = User::query()->create(['email' => 'stranger@example.com', 'name' => 'Stranger', 'password' => 'secret']);

        UserSettings::of($this->user)->save([Settings::FIELD_IS_ENABLED => true, Settings::FIELD_API_KEY => 'sk-owner', Settings::FIELD_MODEL => 'owner-model']);
    }

    // public
    public function test_a_grant_works_only_after_it_is_accepted(): void
    {
        $grant = Grants::of($this->user)->give($this->friend);
        $consents = Consents::of($this->friend);

        $this->assertNull(Access::for($this->friend));
        $this->assertSame($grant->id, Access::offerFor($this->friend)->id);
        $this->assertTrue($consents->isUndecided($grant));

        $consents->accept($grant);

        $this->assertSame($grant->id, Access::for($this->friend)->grant->id);
        $this->assertNull(Access::offerFor($this->friend));
        $this->assertTrue($consents->isAccepted($grant));

        $consents->decline($grant);

        $this->assertNull(Access::for($this->friend));
        $this->assertNull(Access::offerFor($this->friend));
        $this->assertTrue($consents->isDeclined($grant));
        $this->assertSame(1, GrantConsent::query()->count());

        $consents->accept($grant);

        $this->assertNotNull(Access::for($this->friend));
        $this->assertSame(1, GrantConsent::query()->count());
    }

    public function test_everybody_in_the_conversation_decides_for_themselves(): void
    {
        $grant = Grants::of($this->user)->give(null, 'chat:1');

        Consents::of($this->friend)->accept($grant);
        Consents::of($this->stranger)->decline($grant);

        $this->assertTrue(Access::for($this->friend, 'chat:1')->isShared());
        $this->assertNull(Access::for($this->stranger, 'chat:1'));
        $this->assertNull(Access::offerFor($this->stranger, 'chat:1'));
        $this->assertNull(Access::for($this->friend, 'chat:2'));
    }

    public function test_an_acceptance_holds_for_the_endpoint_accepted(): void
    {
        UserSettings::of($this->user)->save([Settings::FIELD_DRIVER => 'openai', Settings::FIELD_URL => 'https://llm.example.com/v1/']);

        $grant = Grants::of($this->user)->give($this->friend);
        $consent = Consents::of($this->friend)->accept($grant);

        $this->assertSame('openai https://llm.example.com/v1', $consent->endpoint());
        $this->assertNotNull(Access::for($this->friend));

        UserSettings::of($this->user)->save([Settings::FIELD_URL => 'https://evil.example.net/v1']);

        $this->assertNull(Access::for($this->friend));
        $this->assertSame($grant->id, Access::offerFor($this->friend)->id);
        $this->assertTrue(Consents::of($this->friend)->isUndecided($grant));

        UserSettings::of($this->user)->save([Settings::FIELD_URL => 'https://llm.example.com/v1']);

        $this->assertNotNull(Access::for($this->friend));
    }

    public function test_only_an_offered_grant_is_decided_on(): void
    {
        $grants = Grants::of($this->user);
        $personal = $grants->give($this->friend);
        $here = $grants->give($this->friend, 'chat:1');

        $this->assertThrows(fn () => Consents::of($this->stranger)->accept($personal), DomainException::class);
        $this->assertThrows(fn () => Consents::of($this->user)->accept($grants->give(null, 'chat:1')), DomainException::class);

        $grants->revoke($here);

        $this->assertThrows(fn () => Consents::of($this->friend)->accept($here), DomainException::class);
        $this->assertSame(0, GrantConsent::query()->count());
    }

    public function test_the_offer_prefers_a_personal_grant_and_skips_unready_owners(): void
    {
        $other = User::query()->create(['email' => 'other@example.com', 'name' => 'Other', 'password' => 'secret']);
        Grants::of($other)->give($this->friend, 'chat:1');

        $everybody = Grants::of($this->user)->give(null, 'chat:1');
        $personal = Grants::of($this->user)->give($this->friend);

        $this->assertSame($personal->id, Access::offerFor($this->friend, 'chat:1')->id);

        Consents::of($this->friend)->decline($personal);

        $this->assertSame($everybody->id, Access::offerFor($this->friend, 'chat:1')->id);

        UserSettings::of($this->friend)->save([Settings::FIELD_IS_ENABLED => true, Settings::FIELD_API_KEY => 'sk-friend']);

        $this->assertNull(Access::offerFor($this->friend, 'chat:1'));
    }
}
