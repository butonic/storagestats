<?php
/**
 * ownCloud - storagestats
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Jörn Friedrich Dreyer <jfd@owncloud.com>
 * @copyright Jörn Friedrich Dreyer 2015
 */

namespace OCA\StorageStats\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Http\DataResponse;
use \OCP\AppFramework\Controller;

class PageController extends Controller {


	private $userId;

	public function __construct($AppName, IRequest $request, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index() {
		$params = ['user' => $this->userId];
		return new TemplateResponse('storagestats', 'main', $params);  // templates/main.php
	}

	/**
	 * Simply method that posts back the payload of the request
	 * @NoAdminRequired
	 */
	public function doEcho($echo) {
		return new DataResponse(['echo' => $echo]);
	}

	/**
	 * anzahl dateien eingeteilt in größen
	 * // FIXME permissions
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getFileCount() {
		$bottom = 0;
		$sql = "SELECT sum(case when size = 0 then 1 else 0 end) as '0 B'";
		for ($i = 0; $i < 32; $i++) {
			$top = pow(2, $i);
			$htop = \OCP\Util::humanFileSize($top);
			$sql .= ", sum(case when size > $bottom and size <= $top then 1 else 0 end) as '<=$htop'";
			$bottom = $top;
		}
		$sql .= ", sum(case when size > $top then 1 else 0 end) as '>$htop'
			FROM *PREFIX*filecache";
		// FIXME ohne dirs
		// TODO für einzelne user?
		$result = \OC::$server->getDatabaseConnection()->executeQuery($sql);
		$fileCount = $result->fetch(\PDO::FETCH_ASSOC);
		$result->closeCursor();
		return new JSONResponse($fileCount);
	}

	/**
	 * anzahl dateien eingeteilt in größen
	 * // FIXME permissions
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getFileTypeStats($bottom = 0, $top = -1) {
		$bottom = 0;
		$sql = "SELECT m.mimetype, count(*) 'count', avg(size) 'avg'
 			FROM *PREFIX*filecache f
			JOIN *PREFIX*mimetypes m ON f.mimetype = m.id
			WHERE m.mimetype != 'httpd/unix-directory'
			GROUP BY m.mimetype";

		// FIXME ohne dirs
		// TODO für einzelne user?
		$result = \OC::$server->getDatabaseConnection()->executeQuery($sql);
		$response = [];
		while ($fileTypeStat = $result->fetch(\PDO::FETCH_ASSOC)) {
			$response [$fileTypeStat['mimetype']] = [
				'count' => (int)$fileTypeStat['count'],
				'avg' => (int)$fileTypeStat['avg'],
			];
		};
		$result->closeCursor();
		return new JSONResponse($response);
	}
}