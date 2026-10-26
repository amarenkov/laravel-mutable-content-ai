<?php

namespace Amarenkov\MutableContentAi\Services;

use DateTimeInterface;
use DomainException;

use Illuminate\Contracts\Auth\Authenticatable;

use Amarenkov\MutableContentAi\Lovs\FeedbackStatus;
use Amarenkov\MutableContentAi\Models\Feedback;
use Amarenkov\MutableContentAi\Models\Run;

/**
 * Marks of one user on agent messages, to review them and to correct the prompts:
 * Feedbacks::of($user)->add($reaction, $message, $run, $context, $source) / withdraw($source) / comment() / resolve() / reopen().
 * The source is the application's key of the marked message: one feedback per message, a withdrawn mark removes it.
 */
class Feedbacks
{
    // static
    public static function of(Authenticatable|int $user): self
    {
        return new self($user instanceof Authenticatable ? (int)$user->getAuthIdentifier() : $user);
    }

    // protected
    protected function save(Feedback $feedback, array $fields): Feedback
    {
        $feedback->fill($fields);
        $feedback->withLogContext()->user($this->userId);
        $feedback->save();

        return $feedback;
    }

    protected function ensureOwn(Feedback $feedback): void
    {
        if ((int)$feedback->getField(Feedback::FIELD_USER_ID) !== $this->userId) {
            throw new DomainException(__('mutable-content-ai::ai.feedback_not_found'));
        }
    }

    /**
     * @return array<array{author: string, text: string, at: ?string, rated: bool}>
     */
    protected function normalizeContext(array $context): array
    {
        return array_values(array_map(fn (array $entry) => [
            'author' => (string)($entry['author'] ?? ''),
            'text' => (string)($entry['text'] ?? ''),
            'at' => ($entry['at'] ?? null) instanceof DateTimeInterface ? $entry['at']->format(DATE_ATOM) : ($entry['at'] ?? null),
            'rated' => (bool)($entry['rated'] ?? false),
        ], $context));
    }

    // public
    public function __construct(
        public readonly int $userId,
    )
    {
    }

    /**
     * Keep a mark on an agent message. With a source the message is marked once: a new reaction replaces the old one.
     *
     * @param array<array{author: string, text: string, at?: DateTimeInterface|string|null, rated?: bool}> $context
     */
    public function add(string $reaction, string $message, ?Run $run = null, array $context = [], ?string $source = null, ?string $conversation = null): Feedback
    {
        $existing = $source === null ? null : Feedback::query()->ownedBy($this->userId)->whereField(Feedback::FIELD_SOURCE, $source)->first();

        if ($existing) {
            return $existing->reaction() === $reaction ? $existing : $this->save($existing, [Feedback::FIELD_REACTION => $reaction]);
        }

        return $this->save(new Feedback(), array_filter([
            Feedback::FIELD_USER_ID => $this->userId,
            Feedback::FIELD_RUN_ID => $run?->id,
            Feedback::FIELD_CONVERSATION => $conversation ?? $run?->getField(Run::FIELD_CONVERSATION),
            Feedback::FIELD_SOURCE => $source,
            Feedback::FIELD_REACTION => $reaction,
            Feedback::FIELD_MESSAGE => $message,
            Feedback::FIELD_CONTEXT => $context ? json_encode($this->normalizeContext($context), JSON_UNESCAPED_UNICODE) : null,
            Feedback::FIELD_STATUS => FeedbackStatus::NEW,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * The mark was taken back: remove the new feedback of the source; a resolved one stays. False when there is none.
     */
    public function withdraw(string $source): bool
    {
        $feedback = Feedback::query()->ownedBy($this->userId)->whereField(Feedback::FIELD_SOURCE, $source)->withStatus(FeedbackStatus::NEW)->first();

        if (!$feedback) {
            return false;
        }

        $feedback->withLogContext()->user($this->userId);
        $feedback->delete();

        return true;
    }

    /**
     * A feedback of this user; null when there is no such one.
     */
    public function find(int $id): ?Feedback
    {
        return Feedback::query()->ownedBy($this->userId)->find($id);
    }

    public function resolve(Feedback $feedback, ?string $note = null): Feedback
    {
        $this->ensureOwn($feedback);

        return $this->save($feedback, [Feedback::FIELD_STATUS => FeedbackStatus::RESOLVED] + ($note === null ? [] : [Feedback::FIELD_NOTE => $note]));
    }

    public function reopen(Feedback $feedback): Feedback
    {
        $this->ensureOwn($feedback);

        return $this->save($feedback, [Feedback::FIELD_STATUS => FeedbackStatus::NEW]);
    }

    /**
     * What was found or changed about the message; an empty note removes it.
     */
    public function note(Feedback $feedback, ?string $note): Feedback
    {
        $this->ensureOwn($feedback);

        $note = trim((string)$note);

        return $this->save($feedback, [Feedback::FIELD_NOTE => $note === '' ? null : $note]);
    }

    /**
     * The user's words on what is wrong with the message; an empty comment removes it.
     */
    public function comment(Feedback $feedback, ?string $comment): Feedback
    {
        $this->ensureOwn($feedback);

        $comment = trim((string)$comment);

        return $this->save($feedback, [Feedback::FIELD_COMMENT => $comment === '' ? null : $comment]);
    }

    public function delete(Feedback $feedback): void
    {
        $this->ensureOwn($feedback);

        $feedback->withLogContext()->user($this->userId);
        $feedback->delete();
    }
}
