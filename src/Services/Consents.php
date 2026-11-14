<?php

namespace Amarenkov\MutableContentAi\Services;

use DomainException;

use Amarenkov\MutableContentAi\Services\Concerns\ForUser;
use Amarenkov\MutableContentAi\Models\Grant;
use Amarenkov\MutableContentAi\Models\GrantConsent;

/**
 * Decisions of one user on the grants offered to them: Consents::of($user)->accept($grant) / decline($grant) /
 * isAccepted($grant) / isDeclined($grant). A grant works for the user only after they accepted it, and only while
 * the owner's settings point to the endpoint accepted: somebody else's model sees the user's requests and data
 * and answers with actions in the user's name.
 */
class Consents
{
    use ForUser;

    // static
    // protected
    protected function ensureOffered(Grant $grant): void
    {
        $offered = !$grant->isRevoked()
            && $grant->ownerId() !== $this->userId
            && ($grant->isForEverybody() || $grant->userId() === $this->userId);

        if (!$offered) {
            throw new DomainException(__('mutable-content-ai::ai.grant_not_offered'));
        }
    }

    protected function decide(Grant $grant, bool $accepted): GrantConsent
    {
        $this->ensureOffered($grant);

        $fields = [
            GrantConsent::FIELD_IS_ACCEPTED => $accepted,
            GrantConsent::FIELD_ENDPOINT => $accepted ? UserSettings::of($grant->ownerId())->endpoint() : null,
        ];

        $consent = $this->find($grant);

        if ($consent) {
            $consent->fill($fields);

            if ($consent->isDirty()) {
                $this->saveAsUser($consent);
            }

            return $consent;
        }

        $consent = new GrantConsent([GrantConsent::FIELD_GRANT_ID => $grant->id, GrantConsent::FIELD_USER_ID => $this->userId] + $fields);
        $this->saveAsUser($consent);

        return $consent;
    }

    // public
    public function find(Grant $grant): ?GrantConsent
    {
        return GrantConsent::query()->ownedBy($this->userId)->whereField(GrantConsent::FIELD_GRANT_ID, $grant->id)->first();
    }

    public function accept(Grant $grant): GrantConsent
    {
        return $this->decide($grant, true);
    }

    /**
     * Decline an offered grant or take an acceptance back.
     */
    public function decline(Grant $grant): GrantConsent
    {
        return $this->decide($grant, false);
    }

    /**
     * Accepted, and the owner's settings still point to the endpoint accepted.
     */
    public function isAccepted(Grant $grant): bool
    {
        $consent = $this->find($grant);

        return $consent !== null && $consent->isAccepted() && $consent->endpoint() === UserSettings::of($grant->ownerId())->endpoint();
    }

    public function isDeclined(Grant $grant): bool
    {
        $consent = $this->find($grant);

        return $consent !== null && !$consent->isAccepted();
    }

    /**
     * Neither accepted for the current endpoint nor declined: the user is to be asked.
     */
    public function isUndecided(Grant $grant): bool
    {
        return !$this->isAccepted($grant) && !$this->isDeclined($grant);
    }
}
