<?php
/******************************************************************************
 * Author: Petr Suchy (xsuchy09) <suchy@wamos.cz> <https://www.wamos.cz>
 * Project: Visitor
 * Date: 29.4.19
 * Time: 14:38
 * Copyright: (c) Petr Suchy (xsuchy09) <suchy@wamos.cz> <http://www.wamos.cz>
 *****************************************************************************/


namespace Visitor;


use DateInterval;
use DateTime;
use Exception;
use Hashids\Hashids;
use PDO;
use stdClass;
use UtmCookie\UtmCookie;


/**
 * Description of Visitor
 *
 * @author Petr Suchy (xsuchy09) <suchy@wamos.cz> <http://www.wamos.cz>
 */
class Visitor
{

	public const DB_TABLE_NAME = 'visitor';
	public const COOKIE_NAME = 'visitor';
	public const COOKIE_VALIDITY = 'P10Y';
	public const COOKIE_PATH = '/';
	public const COOKIE_DOMAIN = '';
	public const COOKIE_SECURE = false;
	public const COOKIE_HTTPONLY = false;
	public const COOKIE_SAMESITE = null;
	public const HASHIDS_MIN_LENGTH = 16;

	/**
	 * @var string
	 */
	private $cookieName = self::COOKIE_NAME;

	/**
	 * @var DateInterval|null
	 */
	private $cookieValidityInterval = null;

	/**
	 * @var
	 */
	private $cookiePath = self::COOKIE_PATH;

	/**
	 * @var string
	 */
	private $cookieDomain = self::COOKIE_DOMAIN;

	/**
	 * @var bool
	 */
	private $cookieSecure = self::COOKIE_SECURE;

	/**
	 * @var bool
	 */
	private $cookieHttpOnly = self::COOKIE_HTTPONLY;

	/**
	 * @var string|null
	 */
	private $cookieSameSite = self::COOKIE_SAMESITE;

	/**
	 * @var string
	 */
	private $dbTableName = self::DB_TABLE_NAME;

	/**
	 * @var string|null
	 */
	private $hashidsKey = null;

	/**
	 * @var int
	 */
	private $hashidsMinLength = self::HASHIDS_MIN_LENGTH;
	
	/**
	 * @var PDO
	 */
	private $pdo;
	
	/**
	 * @var Hashids
	 */
	private $hashids;
	
	/**
	 * @var int
	 */
	private $visitorId;
	
	/**
	 * @var string
	 */
	private $visitorHashids;

	/**
	 * Visitor constructor.
	 *
	 * @param PDO               $pdo
	 * @param string            $hashidsKey
	 * @param int|null          $hashidsMinLength
	 * @param string|null       $dbTableName
	 * @param string|null       $cookieName
	 * @param DateInterval|null $cookieValidityInterval
	 * @param string|null       $cookiePath
	 * @param string|null       $cookieDomain
	 * @param bool|null         $cookieSecure
	 * @param bool|null         $cookieHttpOnly
	 *
	 * @throws Exception
	 */
	public function __construct(PDO $pdo,
	                            string $hashidsKey,
	                            ?int $hashidsMinLength = null,
	                            ?string $dbTableName = null,
	                            ?string $cookieName = null,
	                            ?DateInterval $cookieValidityInterval = null,
	                            ?string $cookiePath = null,
	                            ?string $cookieDomain = null,
	                            ?bool $cookieSecure = null,
	                            ?bool $cookieHttpOnly = null,
								?string $cookieSameSite = null)
	{
		// required params
		$this->pdo = $pdo;
		$this->hashidsKey = $hashidsKey;
		// optionally params
		if ($hashidsMinLength !== null) {
			$this->hashidsMinLength = $hashidsMinLength;
		}
		if ($dbTableName !== null) {
			$this->dbTableName = $dbTableName;
		}
		if ($cookieName !== null) {
			$this->cookieName = $cookieName;
		}
		$this->cookieValidityInterval = $cookieValidityInterval;
		if ($this->cookieValidityInterval === null) {
			$this->cookieValidityInterval = new DateInterval(self::COOKIE_VALIDITY);
		}
		if ($cookiePath !== null) {
			$this->cookiePath = $cookiePath;
		}
		if ($cookieDomain !== null) {
			$this->cookieDomain = $cookieDomain;
		}
		if ($cookieSecure !== null) {
			$this->cookieSecure = $cookieSecure;
		}
		if ($cookieHttpOnly !== null) {
			$this->cookieHttpOnly = $cookieHttpOnly;
		}
		$this->cookieSameSite = $cookieSameSite;

		// init
		$this->hashids = new Hashids($this->hashidsKey, $this->hashidsMinLength);
	}
	
	/**
	 * Get cookie value (hashids of visitor).
	 *
	 * @return string|null
	 * @throws Exception
	 */
	protected function getCookie(): ?string
	{
		$cookie = filter_input(INPUT_COOKIE, $this->cookieName);
		if ($cookie !== null && true === $this->checkVisitor($cookie)) {
			$this->setCookie();
			return $this->visitorHashids;
		}
		return null;
	}
	
	/**
	 * Set cookie of visitor.
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function setCookie(): bool
	{
		$expire = new DateTime();
		$expire->add($this->cookieValidityInterval);
		$options = [
			'expires' => $expire->getTimestamp(),
			'path' => $this->cookiePath,
			'domain' => $this->cookieDomain,
			'secure' => $this->cookieSecure,
			'httponly' => $this->cookieHttpOnly,
			'samesite' => (string)$this->cookieSameSite,
		];
		if (PHP_VERSION_ID >= 70300) {
			return setcookie($this->cookieName, $this->visitorHashids, $options);
		} else {
			return setcookie(
				$this->cookieName,
				$this->visitorHashids,
				$options['expires'],
				$options['path'] . ($this->cookieSameSite ? sprintf('; SameSite=%s', $this->cookieSameSite) : ''),
				$options['domain'],
				$options['secure'],
				$options['httponly']
			);
		}
	}
	
	/**
	 * Get visitor id.
	 *
	 * @param string|null $cookie
	 *
	 * @return int|null
	 * @throws Exception
	 */
	public function getVisitorId(?string $cookie = null): ?int
	{
		// cli or bot ... don't add visit ...
		if (PHP_SAPI === 'cli' || $this->botDetected() === true) {
			return null; // cron, bot ...
		}
		if ($cookie === null) {
			$cookie = $this->getCookie();
		}
		if ($cookie === null) {
			$this->createVisitor();
			$this->setCookie();
		}
		return $this->visitorId;
	}

	/**
	 * Get visitor hashids. Just an alias to Visitor::getCookie.
	 *
	 * @return string|null
	 * @throws Exception
	 */
	public function getVisitorHashids(): ?string
	{
		return $this->getCookie();
	}
	
	/**
	 * Check visitor if exists (cookie value etc).
	 * 
	 * @param string $visitorHashids
	 * 
	 * @return bool
	 */
	protected function checkVisitor(string $visitorHashids): bool
	{
		$visitorInfo = $this->hashids->decode($visitorHashids);
		if (false === isset($visitorInfo[0]) || (int)$visitorInfo[0] === 0) {
			return false;
		}
		$visitorId = (int)$visitorInfo[0];
		$sql = '
			SELECT 
				visitor_id, 
				hashids 
			FROM 
				' . $this->dbTableName . ' 
			WHERE 
					visitor_id = :visitor_id 
				AND 
					hashids = :hashids
			';
		$stmt = $this->pdo->prepare($sql);
		$stmt->bindValue(':visitor_id', $visitorId, PDO::PARAM_INT);
		$stmt->bindValue(':hashids', $visitorHashids);
		$stmt->execute();
		$visitor = $stmt->fetchObject();
		if ($stmt->rowCount() === 1) {
			$this->visitorId = (int)$visitor->visitor_id;
			$this->visitorHashids = $visitor->hashids;
			
			return true;
		}

		return false;
	}
	
	/**
	 * Add visit to visitor.
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function addVisit(): bool
	{
		if ($this->getVisitorId() === null) {
			return false;
		}
		
		$sqlUpdate = '
			UPDATE 
				' . $this->dbTableName . ' 
			SET 
				last_visit = NOW() 
			WHERE 
				visitor_id = :visitor_id
			';
		$stmtUpdate = $this->pdo->prepare($sqlUpdate);
		$stmtUpdate->bindValue(':visitor_id', $this->visitorId, PDO::PARAM_INT);
		return $stmtUpdate->execute();
	}
	
	/**
	 * Check if visitor is bot.
	 * 
	 * @return bool
	 */
	public function botDetected(): bool
	{
		$userAgent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT');
		if ($userAgent !== null && preg_match('/bot|crawl|slurp|spider|curl|facebook|fetch|mediapartner/i', $userAgent) === 1) {
			return true;
		}
		return false;
	}
	
	/**
	 * Create visitor.
	 * 
	 * @return bool
	 */
	protected function createVisitor(): bool
	{
		$sql = '
			INSERT INTO 
				' . $this->dbTableName . ' (
					ip_address, 
					hostname, 
					request_uri, 
					http_referer, 
					remote_port, 
					user_agent, 
					visits_count, 
					last_visit, 
					created, 
					utm_source, 
					utm_medium, 
					utm_campaign, 
					utm_term, 
					utm_content 
				) VALUES (
					:ip_address, 
					:hostname, 
					:request_uri, 
					:http_referer, 
					:remote_port, 
					:user_agent, 
					1, 
					NOW(), 
					NOW(), 
					:utm_source, 
					:utm_medium, 
					:utm_campaign, 
					:utm_term, 
					:utm_content
				)
			';
		$stmt = $this->pdo->prepare($sql);
		$stmt->bindValue(':ip_address', filter_input(INPUT_SERVER, 'REMOTE_ADDR'), PDO::PARAM_STR);
		$stmt->bindValue(':hostname', gethostbyaddr(filter_input(INPUT_SERVER, 'REMOTE_ADDR')), PDO::PARAM_STR);
		$stmt->bindValue(':request_uri', filter_input(INPUT_SERVER, 'REQUEST_URI'), PDO::PARAM_STR);
		$stmt->bindValue(':http_referer', filter_input(INPUT_SERVER, 'HTTP_REFERER'), PDO::PARAM_STR);
		$stmt->bindValue(':remote_port', filter_input(INPUT_SERVER, 'REMOTE_PORT'), PDO::PARAM_STR);
		$stmt->bindValue(':user_agent', filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'), PDO::PARAM_STR);
		$stmt->bindValue(':utm_source', UtmCookie::get('source'), PDO::PARAM_STR);
		$stmt->bindValue(':utm_medium', UtmCookie::get('medium'), PDO::PARAM_STR);
		$stmt->bindValue(':utm_campaign', UtmCookie::get('campaign'), PDO::PARAM_STR);
		$stmt->bindValue(':utm_term', UtmCookie::get('term'), PDO::PARAM_STR);
		$stmt->bindValue(':utm_content', UtmCookie::get('content'), PDO::PARAM_STR);
		if ($stmt->execute() === true) {
			$this->visitorId = $this->pdo->lastInsertId();
			$this->visitorHashids = $this->hashids->encode($this->visitorId);
			
			$sqlUpdate = '
				UPDATE 
					' . $this->dbTableName . ' 
				SET 
					hashids = :hashids 
				WHERE 
					visitor_id = :visitor_id
				';
			$stmtUpdate = $this->pdo->prepare($sqlUpdate);
			$stmtUpdate->bindValue(':hashids', $this->visitorHashids, PDO::PARAM_STR);
			$stmtUpdate->bindValue(':visitor_id', $this->visitorId, PDO::PARAM_INT);
			if ($stmtUpdate->execute() === true) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get data about visitor.
	 *
	 * @return stdClass|null
	 * @throws Exception
	 */
	public function getVisitorData(): ?stdClass
	{
		if ($this->getVisitorId() === null) {
			return null;
		}
		$sql = '
			SELECT 
				* 
			FROM 
				' . $this->dbTableName . ' 
			WHERE 
				visitor_id = :visitor_id
				';
		$stmt = $this->pdo->prepare($sql);
		$stmt->bindValue(':visitor_id', $this->getVisitorId());
		if ($stmt->execute() === true) {
			return $stmt->fetchObject();
		}
		return null;
	}

	/**
	 * Get date (with time) of first visit of visitor.
	 *
	 * @return DateTime
	 * @throws Exception
	 */
	public function getVisitorFirstVisitDate(): DateTime
	{
		$data = $this->getVisitorData();
		if ($data === null) {
			return new DateTime();
		}
		return new DateTime($data->created);
	}

	/**
	 * @return string
	 */
	public function getCookieName(): string
	{
		return $this->cookieName;
	}

	/**
	 * @return mixed
	 */
	public function getCookieValidityInterval()
	{
		return $this->cookieValidityInterval;
	}

	/**
	 * @return mixed
	 */
	public function getCookiePath()
	{
		return $this->cookiePath;
	}

	/**
	 * @return string
	 */
	public function getCookieDomain(): string
	{
		return $this->cookieDomain;
	}

	/**
	 * @return bool
	 */
	public function isCookieSecure(): bool
	{
		return $this->cookieSecure;
	}

	/**
	 * @return bool
	 */
	public function isCookieHttpOnly(): bool
	{
		return $this->cookieHttpOnly;
	}

	/**
	 * @return string|null
	 */
	public function getCookieSameSite(): ?string
	{
		return $this->cookieSameSite;
	}
}
