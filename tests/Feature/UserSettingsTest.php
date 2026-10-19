<?php

namespace Amarenkov\MutableContentAi\Tests\Feature;

use LogicException;
use RuntimeException;

use Illuminate\Support\Facades\DB;

use Laravel\Ai\Ai;

use Amarenkov\MutableContentAi\Models\Settings;
use Amarenkov\MutableContentAi\Services\UserSettings;

use Amarenkov\MutableContentAi\Tests\Fixtures\Agents\Helper;
use Amarenkov\MutableContentAi\Tests\Fixtures\Models\User;
use Amarenkov\MutableContentAi\Tests\TestCase;

class UserSettingsTest extends TestCase
{
    // public
    public function test_settings_are_saved_with_an_encrypted_key_kept_when_empty(): void
    {
        $settings = UserSettings::of($this->user);

        $this->assertNull($settings->record());
        $this->assertFalse($settings->isReady());

        $settings->save([Settings::FIELD_IS_ENABLED => true, Settings::FIELD_API_KEY => 'sk-123', Settings::FIELD_MODEL => ' gpt-x ']);

        $record = UserSettings::of($this->user)->record();
        $stored = DB::table($record->getTable())->where('id', $record->id)->value('fields');

        $this->assertStringNotContainsString('sk-123', $stored);
        $this->assertTrue(UserSettings::of($this->user)->isReady());
        $this->assertSame('gpt-x', UserSettings::of($this->user)->model());

        UserSettings::of($this->user)->save([Settings::FIELD_API_KEY => '', Settings::FIELD_DAILY_LIMIT => 5]);

        $this->assertSame('sk-123', UserSettings::of($this->user)->record()->getSecret(Settings::FIELD_API_KEY));
        $this->assertSame(5, UserSettings::of($this->user)->dailyLimit());

        UserSettings::of($this->user)->forgetApiKey();

        $this->assertFalse(UserSettings::of($this->user)->isReady());
        $this->assertSame(1, Settings::query()->count());
    }

    public function test_settings_belong_to_their_user(): void
    {
        $other = User::query()->create(['email' => 'other@example.com', 'name' => 'Other', 'password' => 'secret']);

        UserSettings::of($this->user)->save([Settings::FIELD_IS_ENABLED => true, Settings::FIELD_API_KEY => 'sk-1']);
        UserSettings::of($other)->save([Settings::FIELD_USER_ID => $this->user->id, Settings::FIELD_API_KEY => 'sk-2']);

        $this->assertSame('sk-1', UserSettings::of($this->user)->record()->getSecret(Settings::FIELD_API_KEY));
        $this->assertSame($other->id, (int)UserSettings::of($other)->record()->getField(Settings::FIELD_USER_ID));
        $this->assertFalse(UserSettings::of($other)->isReady());
    }

    public function test_provider_is_built_from_the_settings(): void
    {
        $settings = UserSettings::of($this->user);

        try {
            $settings->providerConfig();
            $this->fail('No API key');
        } catch (LogicException) {
        }

        $settings->save([Settings::FIELD_API_KEY => 'sk-123', Settings::FIELD_URL => 'https://llm.example.com/v1/', Settings::FIELD_MODEL => 'qwen']);

        $this->assertSame([
            'driver' => 'openai-compatible',
            'url' => 'https://llm.example.com/v1',
            'key' => 'sk-123',
            'models' => ['text' => ['default' => 'qwen']],
        ], $settings->providerConfig());

        $name = $settings->provider();

        $this->assertSame(UserSettings::PROVIDER_PREFIX.$this->user->id, $name);
        $this->assertSame('qwen', Ai::textProvider($name)->defaultTextModel());
        $this->assertSame($name, $settings->provider());
    }

    public function test_daily_limit_counts_runs_of_today(): void
    {
        $settings = UserSettings::of($this->user);
        $settings->save([Settings::FIELD_DAILY_LIMIT => 1]);

        $this->assertTrue($settings->withinDailyLimit());

        new Helper($this->user)->runs()->failed('Broken', new RuntimeException('Connection reset'), 10);

        $this->assertSame(1, $settings->usedToday());
        $this->assertFalse($settings->withinDailyLimit());

        $settings->save([Settings::FIELD_DAILY_LIMIT => null]);

        $this->assertTrue($settings->withinDailyLimit());
    }
}
