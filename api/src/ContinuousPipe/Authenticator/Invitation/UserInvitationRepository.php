<?php

namespace ContinuousPipe\Authenticator\Invitation;

use ContinuousPipe\Security\Team\Team;
use Ramsey\Uuid\UuidInterface;

interface UserInvitationRepository
{
    /**
     * @param UuidInterface $uuid
     *
     * @throws InvitationNotFound
     *
     * @return UserInvitation
     */
    public function findByUuid(UuidInterface $uuid);

    /**
     * @param string $email
     *
     * @return UserInvitation[]
     */
    public function findByUserEmail($email);

    /**
     * @param UserInvitation $userInvitation
     *
     * @return UserInvitation
     */
    public function save(UserInvitation $userInvitation);

    /**
     * @param UserInvitation $invitation
     *
     * @throws InvitationException
     */
    public function delete(UserInvitation $invitation);

    /**
     * @param Team $team
     *
     * @return UserInvitation[]
     */
    public function findByTeam(Team $team);
}
