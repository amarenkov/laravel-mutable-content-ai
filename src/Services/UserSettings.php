<?php

namespace Amarenkov\MutableContentAi\Services;

use LogicException;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

use Laravel\Ai\Ai;

use Amarenkov\MutableContentAi\Models\Run;
use Amarenkov\MutableContentAi\Models\Settings;

/**
 * Language model settings of one user: UserSettings::of($user)->save([...]) / isReady() / provider() / model().
 * The user brings an own API key; the agent asks the provider built from it: `public function provider()` of the agent
 * returns UserSettings::of($user)->provider().
 */
class UserSettings
{
    // const
    public const PROVIDER_PREFIX = 'mutable_content_ai_user_';

    // static
    public static function of(Authenticatable|int $user): self
    {
        return new self($user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user);
    }

    // protected
    protected ?Settings $record = null;

    // public
    public function __construct(
        public readonly int $userId,
    )
    {
    }

    public function record(): ?Settings
    {
        return $this->record ??= Settings::query()->ownedBy($this->userId)->first();
    }

    /**
     * Save the given fields; an empty API key keeps the stored one (forgetApiKey() removes it).
     *
     * @param array<string, mixed> $values field code => value
     */
    public function save(array $values): Settings
    {
        if (array_key_exists(Settings::FIELD_API_KEY, $values) && ($values[Settings::FIELD_API_KEY] === null || $values[Settings::FIELD_API_KEY] === '')) {
            unset($values[Settings::FIELD_API_KEY]);
        }

        unset($values[Settings::FIELD_USER_ID]);

        $settings = $this->record() ?? new Settings([Settings::FIELD_USER_ID => $this->userId]);
        $settings->fill($values);

        if ($settings->isDirty() || !$settings->exists) {
            $settings->withLogContext()->user($this->userId);
            $settings->save();
        }

        return $this->record = $settings;
    }

    public function forgetApiKey(): void
    {
        $settings = $this->record();

        if (!$settings || !$settings->hasApiKey()) {
            return;
        }

        $settings->setField(Settings::FIELD_API_KEY, null);
        $settings->withLogContext()->user($this->userId);
        $settings->save();
    }

    /**
     * Whether the agent may run for the user: the settings are enabled and have an API key.
     */
    public function isReady(): bool
    {
        $settings = $this->record();

        return $settings !== null && $settings->isEnabled() && $settings->hasApiKey();
    }

    public function model(): ?string
    {
        $model = $this->record()?->getField(Settings::FIELD_MODEL);

        return is_string($model) && trim($model) !== '' ? trim($model) : null;
    }

    public function dailyLimit(): ?int
    {
        return $this->record()?->dailyLimit();
    }

    /**
     * Runs on the settings with any agent today, in the application timezone: the user's own and through the grants.
     */
    public function usedToday(): int
    {
        return Run::query()->paidBy($this->userId)->where('created_at', '>=', Carbon::now(config('app.timezone'))->startOfDay())->count();
    }

    public function withinDailyLimit(): bool
    {
        $limit = $this->dailyLimit();

        return $limit === null || $this->usedToday() < $limit;
    }

    /**
     * Configuration of the laravel/ai provider: the driver (mutable-content-ai.settings.driver by default), the URL,
     * the key and the default text model.
     *
     * @return array<string, mixed>
     */
    public function providerConfig(): array
    {
        $settings = $this->record();
        $key = $settings?->getSecret(Settings::FIELD_API_KEY);

        if ($key === null) {
            throw new LogicException('The user '.$this->userId.' has no API key');
        }

        $driver = $settings->getField(Settings::FIELD_DRIVER);
        $url = $settings->getField(Settings::FIELD_URL);
        $model = $this->model();

        return array_filter([
            'driver' => is_string($driver) && $driver !== '' ? $driver : config('mutable-content-ai.settings.driver'),
            'url' => is_string($url) && $url !== '' ? rtrim($url, '/') : null,
            'key' => $key,
            'models' => $model ? ['text' => ['default' => $model]] : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Name of the provider built from the settings in this process, to return from the provider() method of an agent.
     */
    public function provider(): string
    {
        $name = self::PROVIDER_PREFIX.$this->userId;

        Ai::build(['name' => $name] + $this->providerConfig());

        return $name;
    }
}
