<?php
namespace App\Http\Validation\Rules;

use App\Http\Validation\Rule;
use App\System\Database;
use App\Translation\TranslationManager;
use App\Translation\Translator;

class UniqueUserEmailRule implements Rule
{
    /** @var Database */
    private $db;

    /** @var Translator */
    private $lang;

    /** @var int */
    private $userId = 0;

    public function __construct(Database $db, TranslationManager $translationManager)
    {
        $this->db = $db;
        $this->lang = $translationManager->user();
    }

    public function validate($attribute, $value, array $data)
    {
        if (!strlen($value)) {
            return [];
        }

        $result = $this->db->query(
            $this->db->prepare(
                "SELECT `uid` FROM `" .
                    TABLE_PREFIX .
                    "users` WHERE `email` = '%s' AND `uid` != '%d'",
                [$value, $this->userId]
            )
        );

        if ($result->rowCount()) {
            return [$this->lang->t('email_occupied')];
        }

        return [];
    }

    /**
     * @param int $userId
     * @return $this
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }
}
