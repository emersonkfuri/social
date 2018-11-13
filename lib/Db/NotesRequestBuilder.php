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

namespace OCA\Social\Db;


use daita\MySmallPhpTools\Traits\TArrayTools;
use OCA\Social\Model\ActivityPub\Note;
use OCA\Social\Model\Post;
use OCP\DB\QueryBuilder\IQueryBuilder;

class NotesRequestBuilder extends CoreRequestBuilder {


	use TArrayTools;


	/**
	 * Base of the Sql Insert request
	 *
	 * @return IQueryBuilder
	 */
	protected function getNotesInsertSql(): IQueryBuilder {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert(self::TABLE_SERVER_NOTES);

		return $qb;
	}


	/**
	 * Base of the Sql Update request
	 *
	 * @return IQueryBuilder
	 */
	protected function getNotesUpdateSql(): IQueryBuilder {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update(self::TABLE_SERVER_NOTES);

		return $qb;
	}


	/**
	 * Base of the Sql Select request for Shares
	 *
	 * @return IQueryBuilder
	 */
	protected function getNotesSelectSql(): IQueryBuilder {
		$qb = $this->dbConnection->getQueryBuilder();

		/** @noinspection PhpMethodParametersCountMismatchInspection */
		$qb->select(
			'sn.id', 'sn.to', 'sn.to_array', 'sn.cc', 'sn.bcc', 'sn.content', 'sn.summary',
			'sn.published', 'sn.attributed_to', 'sn.in_reply_to', 'sn.creation'
		)
		   ->from(self::TABLE_SERVER_NOTES, 'sn');

		$this->defaultSelectAlias = 'sn';

		return $qb;
	}


	/**
	 * Base of the Sql Delete request
	 *
	 * @return IQueryBuilder
	 */
	protected function getNotesDeleteSql(): IQueryBuilder {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete(self::TABLE_SERVER_NOTES);

		return $qb;
	}


	/**
	 * @param array $data
	 *
	 * @return Note
	 */
	protected function parseNotesSelectSql($data): Note {
		$note = new Note();
		$note->setId($data['id'])
			 ->setTo($data['to'])
			 ->setToArray(json_decode($data['to_array'], true))
			 ->setCcArray(json_decode($data['cc'], true))
			 ->setBccArray(json_decode($data['bcc']))
			 ->setPublished($data['published']);
		$note->setContent($data['content'])
			 ->setAttributedTo($data['attributed_to'])
			 ->setInReplyTo($data['in_reply_to']);

		return $note;
	}


	/**
	 * @param array $data
	 *
	 * @return Post
	 */
	protected function parsePostsSelectSql($userId, $data): Note {
		$post = new Post($userId);

		$post->setContent($data['content']);

//		$note->setId($data['id'])
//			 ->setTo($data['to'])
//			 ->setToArray(json_decode($data['to_array'], true))
//			 ->setCc(json_decode($data['cc'], true))
//			 ->setBcc(json_decode($data['bcc']));
//		$note->setContent($data['content'])
//			 ->setPublished($data['published'])
//			 ->setAttributedTo($data['attributed_to'])
//			 ->setInReplyTo($data['in_reply_to']);

		return $post;
	}

}
