<?php

namespace AppBundle\Controller;

use AppBundle\Request\InviteUserRequest;
use ContinuousPipe\Authenticator\Invitation\UserInvitation;
use ContinuousPipe\Authenticator\Invitation\UserInvitationRepository;
use ContinuousPipe\Security\Team\Team;
use ContinuousPipe\Security\Team\TeamMembershipRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use FOS\RestBundle\Controller\Annotations\View;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Route(service="api.controller.invitation")
 */
class InvitationController
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var UserInvitationRepository
     */
    private $userInvitationRepository;

    /**
     * @var TeamMembershipRepository
     */
    private $teamMembershipRepository;

    /**
     * @param ValidatorInterface       $validator
     * @param UserInvitationRepository $userInvitationRepository
     * @param TeamMembershipRepository $teamMembershipRepository
     */
    public function __construct(ValidatorInterface $validator, UserInvitationRepository $userInvitationRepository, TeamMembershipRepository $teamMembershipRepository)
    {
        $this->validator = $validator;
        $this->userInvitationRepository = $userInvitationRepository;
        $this->teamMembershipRepository = $teamMembershipRepository;
    }

    /**
     * @Route("/teams/{slug}/invitations", methods={"POST"})
     * @ParamConverter("team", converter="authenticator_team")
     * @ParamConverter("inviteUserRequest", converter="fos_rest.request_body")
     * @Security("is_granted('ADMIN', team)")
     * @View(statusCode=201)
     */
    public function createAction(Team $team, InviteUserRequest $inviteUserRequest)
    {
        $violations = $this->validator->validate($inviteUserRequest);
        if ($violations->count() > 0) {
            return new JsonResponse([
                'error' => $violations->get(0)->getMessage(),
            ], 400);
        }

        $invitation = $this->userInvitationRepository->save(
            new UserInvitation(Uuid::uuid4(), $inviteUserRequest->email, $team->getSlug(), $inviteUserRequest->permissions ?: [], new \DateTime())
        );

        return $invitation;
    }

    /**
     * @Route("/teams/{slug}/invitations", methods={"GET"})
     * @ParamConverter("team", converter="authenticator_team")
     * @Security("is_granted('READ', team)")
     * @View
     */
    public function listAction(Team $team)
    {
        return $this->userInvitationRepository->findByTeam($team);
    }

    /**
     * @Route("/teams/{slug}/invitations/{uuid}", methods={"DELETE"})
     * @ParamConverter("team", converter="authenticator_team")
     * @Security("is_granted('ADMIN', team)")
     * @View
     */
    public function deleteAction(Team $team, $uuid)
    {
        $invitations = $this->userInvitationRepository->findByTeam($team);
        $matchingInvitations = array_filter($invitations, function (UserInvitation $invitation) use ($uuid) {
            return $invitation->getUuid()->toString() == $uuid;
        });

        if (count($matchingInvitations) == 0) {
            throw new NotFoundHttpException(sprintf('Invitation %s not found in team', $uuid));
        }

        $this->userInvitationRepository->delete(reset($matchingInvitations));
    }

    /**
     * @Route("/teams/{slug}/members-status", methods={"GET"})
     * @ParamConverter("team", converter="authenticator_team")
     * @Security("is_granted('READ', team)")
     * @View
     */
    public function membersStatusAction(Team $team)
    {
        return [
            'memberships' => $this->teamMembershipRepository->findByTeam($team)->toArray(),
            'invitations' => $this->userInvitationRepository->findByTeam($team),
        ];
    }
}
