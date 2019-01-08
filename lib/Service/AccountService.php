<?php
declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Social\Service;


use daita\MySmallPhpTools\Traits\TArrayTools;
use Exception;
use OC\User\NoUserException;
use OCA\Social\Db\ActorsRequest;
use OCA\Social\Db\FollowsRequest;
use OCA\Social\Db\NotesRequest;
use OCA\Social\Exceptions\AccountAlreadyExistsException;
use OCA\Social\Exceptions\ActorDoesNotExistException;
use OCA\Social\Exceptions\ItemUnknownException;
use OCA\Social\Exceptions\SocialAppConfigException;
use OCA\Social\Exceptions\UrlCloudException;
use OCA\Social\Model\ActivityPub\Actor\Person;
use OCP\Accounts\IAccountManager;
use OCP\IUserManager;


/**
 * Class ActorService
 *
 * @package OCA\Social\Service
 */
class AccountService {


	const KEY_PAIR_LIFESPAN = 7;


	use TArrayTools;


	/** @var IUserManager */
	private $userManager;

	/** @var IAccountManager */
	private $accountManager;

	/** @var ActorsRequest */
	private $actorsRequest;

	/** @var FollowsRequest */
	private $followsRequest;

	/** @var NotesRequest */
	private $notesRequest;

	/** @var ActorService */
	private $actorService;

	/** @var SignatureService */
	private $signatureService;

	/** @var DocumentService */
	private $documentService;

	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * ActorService constructor.
	 *
	 * @param IUserManager $userManager
	 * @param IAccountManager $accountManager
	 * @param ActorsRequest $actorsRequest
	 * @param FollowsRequest $followsRequest
	 * @param NotesRequest $notesRequest
	 * @param ActorService $actorService
	 * @param DocumentService $documentService
	 * @param SignatureService $signatureService
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IUserManager $userManager, IAccountManager $accountManager, ActorsRequest $actorsRequest,
		FollowsRequest $followsRequest, NotesRequest $notesRequest, ActorService $actorService,
		DocumentService $documentService, SignatureService $signatureService,
		ConfigService $configService, MiscService $miscService
	) {
		$this->userManager = $userManager;
		$this->accountManager = $accountManager;
		$this->actorsRequest = $actorsRequest;
		$this->followsRequest = $followsRequest;
		$this->notesRequest = $notesRequest;
		$this->actorService = $actorService;
		$this->documentService = $documentService;
		$this->signatureService = $signatureService;
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param string $username
	 *
	 * @return Person
	 * @throws ActorDoesNotExistException
	 * @throws SocialAppConfigException
	 */
	public function getActor(string $username): Person {
		$actor = $this->actorsRequest->getFromUsername($username);

		return $actor;
	}

	/**
	 * @param string $id
	 *
	 * @return Person
	 * @throws ActorDoesNotExistException
	 * @throws SocialAppConfigException
	 */
	public function getFromId(string $id): Person {
		$actor = $this->actorsRequest->getFromId($id);

		return $actor;
	}


	/**
	 * @param string $userId
	 * @param bool $create
	 *
	 * @return Person
	 * @throws AccountAlreadyExistsException
	 * @throws ActorDoesNotExistException
	 * @throws NoUserException
	 * @throws SocialAppConfigException
	 * @throws UrlCloudException
	 * @throws ItemUnknownException
	 */
	public function getActorFromUserId(string $userId, bool $create = false): Person {
		$this->miscService->confirmUserId($userId);
		try {
			$actor = $this->actorsRequest->getFromUserId($userId);
		} catch (ActorDoesNotExistException $e) {
			if ($create) {
				$this->createActor($userId, $userId);
				$actor = $this->actorsRequest->getFromUserId($userId);
			} else {
				throw new ActorDoesNotExistException();
			}
		}

		return $actor;
	}


	/**
	 * Method should be called by the frontend and will generate a fresh Social account for
	 * the user, using the userId and the username.
	 *
	 * Pair of keys are created at this point.
	 *
	 * Return exceptions if an account already exist for this user or if the username is already
	 * taken
	 *
	 * @param string $userId
	 * @param string $username
	 *
	 * @throws AccountAlreadyExistsException
	 * @throws NoUserException
	 * @throws SocialAppConfigException
	 * @throws UrlCloudException
	 * @throws ItemUnknownException
	 */
	public function createActor(string $userId, string $username) {

		$this->miscService->confirmUserId($userId);
		$this->checkActorUsername($username);

		try {
			$this->actorsRequest->getFromUsername($username);
			throw new AccountAlreadyExistsException('actor with that name already exist');
		} catch (ActorDoesNotExistException $e) {
			/* we do nohtin */
		}

		try {
			$this->actorsRequest->getFromUserId($userId);
			throw new AccountAlreadyExistsException('account for this user already exist');
		} catch (ActorDoesNotExistException $e) {
			/* we do nohtin */
		}

		$this->configService->setCoreValue('public_webfinger', 'social/lib/webfinger.php');

		$actor = new Person();
		$actor->setUserId($userId);
		$actor->setPreferredUsername($username);

		$this->signatureService->generateKeys($actor);
		$this->actorsRequest->create($actor);

		// generate cache.
		$this->cacheLocalActorByUsername($username, true);
	}


	/**
	 * @param string $username
	 * @param bool $refresh
	 *
	 * @throws SocialAppConfigException
	 * @throws UrlCloudException
	 * @throws ItemUnknownException
	 */
	public function cacheLocalActorByUsername(string $username, bool $refresh = false) {
		try {
			$actor = $this->getActor($username);

			try {
				$this->updateCacheLocalActorName($actor);
			} catch (NoUserException $e) {
				return;
			}

			$iconId = $this->documentService->cacheLocalAvatarByUsername($actor);
			$actor->setIconId($iconId);

			$count = [
				'followers' => $this->followsRequest->countFollowers($actor->getId()),
				'following' => $this->followsRequest->countFollowing($actor->getId()),
				'post'      => $this->notesRequest->countNotesFromActorId($actor->getId())
			];
			$actor->addDetailArray('count', $count);

			$this->actorService->cacheLocalActor($actor, $refresh);
		} catch (ActorDoesNotExistException $e) {
		}
	}


	/**
	 * @param Person $actor
	 *
	 * @throws NoUserException
	 */
	private function updateCacheLocalActorName(Person &$actor) {
		$user = $this->userManager->get($actor->getUserId());
		if ($user === null) {
			throw new NoUserException();
		}

		try {
			$account = $this->accountManager->getAccount($user);
			$displayNameProperty = $account->getProperty(IAccountManager::PROPERTY_DISPLAYNAME);
			if ($displayNameProperty->getScope() === IAccountManager::VISIBILITY_PUBLIC) {
				$actor->setName($displayNameProperty->getValue());
			}
		} catch (Exception $e) {
			$this->miscService->log(
				'Issue while trying to updateCacheLocalActorName: ' . $e->getMessage(), 1
			);
		}
	}


	/**
	 * @param $username
	 */
	private function checkActorUsername($username) {
		$accepted = 'qwertyuiopasdfghjklzxcvbnm';

		return;
	}


	/**
	 * @throws Exception
	 * @return int
	 */
	public function manageCacheLocalActors(): int {
		$update = $this->actorsRequest->getAll();
		foreach ($update as $item) {
			try {
				$this->cacheLocalActorByUsername($item->getPreferredUsername(), true);
			} catch (Exception $e) {
			}
		}

		return sizeof($update);
	}


	/**
	 * @throws Exception
	 * @return int
	 */
	public function blindKeyRotation(): int {
		$update = $this->actorsRequest->getAll();
		$count = 0;
		foreach ($update as $actor) {
			try {
				if ($actor->getCreation() < (time() - (self::KEY_PAIR_LIFESPAN * 3600 * 24))) {
					$this->signatureService->generateKeys($actor);
					$this->actorsRequest->refreshKeys($actor);
					$count++;
				}
			} catch (Exception $e) {
			}
		}

		return $count;
	}


}
