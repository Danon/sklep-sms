<?php
namespace App\View\Blocks;

use App\Routing\UrlGenerator;
use App\Services\UserServiceAccessService;
use App\Support\Template;
use App\System\Auth;
use App\System\Heart;
use App\Translation\TranslationManager;
use App\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;

class BlockUserButtons extends Block
{
    const BLOCK_ID = "user_buttons";

    /** @var Auth */
    private $auth;

    /** @var Template */
    private $template;

    /** @var Heart */
    private $heart;

    /** @var UrlGenerator */
    private $url;

    /** @var Translator */
    private $lang;

    /** @var UserServiceAccessService */
    private $userServiceAccessService;

    public function __construct(
        Auth $auth,
        Template $template,
        TranslationManager $translationManager,
        Heart $heart,
        UrlGenerator $url,
        UserServiceAccessService $userServiceAccessService
    ) {
        $this->auth = $auth;
        $this->template = $template;
        $this->heart = $heart;
        $this->url = $url;
        $this->lang = $translationManager->user();
        $this->userServiceAccessService = $userServiceAccessService;
    }

    public function getContentClass()
    {
        return is_logged() ? "user_buttons" : "loginarea";
    }

    public function getContentId()
    {
        return "user_buttons";
    }

    protected function content(Request $request, array $params)
    {
        if (!$this->auth->check()) {
            return $this->template->render("loginarea");
        }

        $user = $this->auth->user();
        $acpButton = "";

        if (has_privileges("acp", $user)) {
            $acpButton = $this->template->render("navigation_item", [
                "link" => $this->url->to("/admin"),
                "text" => $this->lang->t("acp"),
            ]);
        }

        if (
            $this->userServiceAccessService->canUserUseService(
                $this->heart->getService("charge_wallet"),
                $user
            )
        ) {
            $chargeWalletButton = $this->template->render("navigation_item", [
                "link" => $this->url->to("/page/purchase?service=charge_wallet"),
                "text" => $this->lang->t("charge_wallet"),
            ]);
        }

        return $this->template->render("user_buttons", compact("acpButton", "chargeWalletButton"));
    }
}
