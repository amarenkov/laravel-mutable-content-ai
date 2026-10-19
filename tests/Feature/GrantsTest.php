<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use DomainException;
use RuntimeException;

use Illuminate\Support\Facades\DB;

use Amarenkov\MutableContentAi\Models\Grant;
use Amarenkov\MutableContentAi\Models\Run;
use Amarenkov\MutableContentAi\Models\Settings;
use Amarenkov\MutableContentAi\Services\Access;
use Amarenkov\MutableContentAi\Services\Grants;
use Amarenkov\MutableContentAi\Services\UserSettings;

use Amarenkov\MutableContentAi\Tests\Fixtures\Agents\Helper;
use Amarenkov\MutableContentAi\Tests\Fixtures\Models\User;
use Amarenkov\MutableContentAi\Tests\TestCase;

class GrantsTest extends TestCase
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

    protected function failRun(User $user, ?Access $access, string $conversation = 'chat:1'): Run
    {
        return new Helper($user, $conversation)->runs()->using($access)->failed('Hi', new RuntimeException('Connection reset'), 10);
    }

    // public
    public function test_a_grant_is_given_once_per_user_and_conversation(): void
    {
        $grants = Grants::of($this->user);

        $grant = $grants->give($this->friend, 'chat:1');

        $this->assertSame($this->friend->id, $grant->userId());
        $this->assertSame('chat:1', $grant->conversation());
        $this->assertNull($grant->dailyLimit());
        $this->assertSame($grant->id, $grants->give($this->friend, 'chat:1')->id);
        $this->assertSame(5, $grants->give($this->friend, 'chat:1', 5)->dailyLimit());
        $this->assertSame(5, $grants->give($this->friend, 'chat:1')->dailyLimit());
        $this->assertNotSame($grant->id, $grants->give($this->friend)->id);
        $this->assertTrue($grants->give(null, 'chat:1')->isForEverybody());
        $this->assertSame(3, $grants->query()->count());

        $this->assertThrows(fn () => $grants->give(null), DomainException::class);
        $this->assertThrows(fn () => $grants->give($this->user, 'chat:1'), DomainException::class);
        $this->assertThrows(fn () => $grants->give($this->friend, 'chat:2', 0), DomainException::class);
        $this->assertThrows(fn () => Grants::of($this->friend)->revoke($grant), DomainException::class);
    }

    public function test_own_ready_settings_come_first(): void
    {
        Grants::of($this->friend)->give($this->user);
        UserSettings::of($this->friend)->save([Settings::FIELD_IS_ENABLED => true, Settings::FIELD_API_KEY => 'sk-friend']);

        $access = Access::for($this->user, 'chat:1');

        $this->assertFalse($access->isShared());
        $this->assertSame($this->user->id, $access->payerId());
        $this->assertSame('owner-model', $access->model());
    }

    public function test_a_grant_works_in_its_conversation_and_the_nested_ones(): void
    {
        $grants = Grants::of($this->user);
        $grant = $grants->give($this->friend, 'chat:1');

        $access = Access::for($this->friend, 'chat:1:7');

        $this->assertTrue($access->isShared());
        $this->assertSame($grant->id, $access->grant->id);
        $this->assertSame($this->user->id, $access->payerId());
        $this->assertSame('owner-model', $access->model());
        $this->assertNotNull(Access::for($this->friend, 'chat:1'));
        $this->assertNull(Access::for($this->friend, 'chat:10'));
        $this->assertNull(Access::for($this->friend));
        $this->assertNull(Access::for($this->stranger, 'chat:1'));

        $grants->moveTo($grant, null);

        $this->assertNotNull(Access::for($this->friend));
        $this->assertNotNull(Access::for($this->friend, 'chat:10'));

        $grants->revoke($grant);

        $this->assertNull(Access::for($this->friend, 'chat:1'));
        $this->assertTrue($grants->find($grant->id, withRevoked: true)->isRevoked());
        $this->assertNull($grants->find($grant->id));
    }

    public function test_a_grant_for_everybody_in_the_conversation(): void
    {
        Grants::of($this->user)->give(null, 'chat:1');

        $this->assertTrue(Access::for($this->friend, 'chat:1')->grant->isForEverybody());
        $this->assertTrue(Access::for($this->stranger, 'chat:1')->isShared());
        $this->assertFalse(Access::for($this->user, 'chat:1')->isShared());
        $this->assertNull(Access::for($this->friend, 'chat:2'));
    }

    public function test_a_grant_needs_ready_settings_of_the_owner(): void
    {
        Grants::of($this->user)->give($this->friend);

        UserSettings::of($this->user)->save([Settings::FIELD_IS_ENABLED => false]);

        $this->assertNull(Access::for($this->friend));
    }

    public function test_moving_to_a_taken_place_revokes_the_duplicate(): void
    {
        $grants = Grants::of($this->user);
        $everywhere = $grants->give($this->friend);
        $here = $grants->give($this->friend, 'chat:1');

        $grants->moveTo($here, null);

        $this->assertSame([$here->id], $grants->query()->pluck('id')->all());
        $this->assertTrue($grants->find($everywhere->id, withRevoked: true)->isRevoked());
        $this->assertThrows(fn () => $grants->moveTo($grants->give(null, 'chat:1'), null), DomainException::class);
    }

    public function test_runs_through_a_grant_are_paid_by_its_owner_within_both_limits(): void
    {
        UserSettings::of($this->user)->save([Settings::FIELD_DAILY_LIMIT => 3]);

        $grants = Grants::of($this->user);
        $personal = $grants->give($this->friend, 'chat:1', 1);
        $everybody = $grants->give(null, 'chat:1');

        $access = Access::for($this->friend, 'chat:1');
        $this->assertSame($personal->id, $access->grant->id);

        $run = $this->failRun($this->friend, $access);

        $this->assertSame($this->friend->id, (int)$run->getField(Run::FIELD_USER_ID));
        $this->assertSame($this->user->id, (int)$run->getField(Run::FIELD_PAYER_ID));
        $this->assertSame($personal->id, $run->grant->id);
        $this->assertSame(1, Grants::usedToday($personal));
        $this->assertSame(1, UserSettings::of($this->user)->usedToday());
        $this->assertSame(0, UserSettings::of($this->friend)->usedToday());

        $access = Access::for($this->friend, 'chat:1');
        $this->assertSame($everybody->id, $access->grant->id);
        $this->assertTrue($access->withinDailyLimit());

        $this->failRun($this->stranger, Access::for($this->stranger, 'chat:1'));
        $this->failRun($this->user, Access::for($this->user, 'chat:1'));

        $access = Access::for($this->friend, 'chat:1');
        $this->assertSame($personal->id, $access->grant->id);
        $this->assertFalse($access->withinDailyLimit());
        $this->assertSame(3, UserSettings::of($this->user)->usedToday());
    }

    public function test_old_runs_without_a_payer_are_paid_by_their_user(): void
    {
        $run = $this->failRun($this->user, null);
        $fields = DB::table($run->getTable())->where('id', $run->id)->value('fields');
        $fields = json_decode($fields, true);
        unset($fields[Run::FIELD_PAYER_ID]);
        DB::table($run->getTable())->where('id', $run->id)->update(['fields' => json_encode($fields)]);

        $this->assertNull(Run::query()->find($run->id)->getField(Run::FIELD_PAYER_ID));
        $this->assertSame(1, UserSettings::of($this->user)->usedToday());
        $this->assertSame(0, Grant::query()->count());
    }
}
