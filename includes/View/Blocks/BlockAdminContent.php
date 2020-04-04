<?php
namespace App\View\Blocks;

use App\System\Heart;
use App\Translation\TranslationManager;
use App\Translation\Translator;
use App\View\CurrentPage;

class BlockAdminContent extends Block
{
    /** @var Heart */
    private $heart;

    /** @var CurrentPage */
    private $page;

    /** @var Translator */
    private $lang;

    public function __construct(
        Heart $heart,
        CurrentPage $page,
        TranslationManager $translationManager
    ) {
        $this->heart = $heart;
        $this->page = $page;
        $this->lang = $translationManager->user();
    }

    public function getContentClass()
    {
        return "custom_content";
    }

    public function getContentId()
    {
        return "content";
    }

    // Nadpisujemy getContent, aby wyswieltac info gdy nie jest zalogowany lub jest zalogowany, lecz nie powinien
    public function getContent(array $query, array $body)
    {
        if (!is_logged()) {
            return $this->lang->t('must_be_logged_in');
        }

        return $this->content($query, $body);
    }

    protected function content(array $query, array $body)
    {
        $page = $this->heart->getPage($this->page->getPid(), "admin");

        if ($page) {
            // Remove pid parametr since we don't want to add it to pagination urls
            unset($query["pid"]);
            return $page->getContent($query, $body);
        }

        return null;
    }
}
