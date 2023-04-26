<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GithubAuthenticator extends OAuth2Authenticator
{
    private ClientRegistry $clientRegistry;
    private RouterInterface $router;
    private EntityManagerInterface $entityManager;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $entityManager, RouterInterface $router)
    {
        $this->clientRegistry = $clientRegistry;
        $this->router = $router;
        $this->entityManager = $entityManager;
    }

    public function supports(Request $request): bool
    {
        return 'github_redirect_url' === $request->attributes->get('_route') && $request->get('service') === 'github';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('github');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken, $client, $request) {

                /** @var GithubResourceOwner $githubUser */
                $githubUser = $client->fetchUserFromToken($accessToken);

                // we get back the email user
                $response = HttpClient::create()->request(
                    'GET',
                    'https://api.github.com/user/emails',
                    [
                        'headers' => [
                            'authorization' => "token $accessToken"
                        ]
                    ]
                );
                $emails = json_decode($response->getContent(), true);

                foreach ($emails as $email) {
                    // We only use primary and verified emails to avoid security breaches.
                    if ($email['primary'] === true && $email['verified'] === true) {
                        $data = $githubUser->toArray();
                        $data['email'] = $email['email'];
                        $githubUser = new GithubResourceOwner($data);
                    }
                }
                // 1) have they logged in with github before? Easy!
                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['githubId' => $githubUser->getId()]);

                if ($existingUser) {
                    return $existingUser;
                }

                // 2) do we have a matching user by email?
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);

                if (!$user) {
                    $user = new User();
                    $user->setGithubId($githubUser->getId());
                    $user->setEmail($githubUser->getEmail() ?? $email['email'] ?? 'test@test.fr');
                    $user->setFullname($githubUser->getNickname());
                    $user->setUsername($githubUser->getNickname());
                    $user->setAvatarUrl($githubUser->getNickname().'_avatar');
                    $user->setProfileHtmlUrl($githubUser->getNickname().'_profile');
                    $user->setPassword('test');
                }
                // 3) Maybe you just want to "register" them by creating
                // a User object
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                return $user;
            })
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) : Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetUrl = $this->router->generate('diary');

        return new RedirectResponse($targetUrl);
    }
}
