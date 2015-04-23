<?php
namespace Mooc;

use Mooc\DB\CoursewareFactory;
use Mooc\UI\BlockFactory;
use Mooc\UI\MustacheRenderer;

/**
 * @author  <mlunzena@uos.de>
 */
class Container extends \Pimple
{
    public function __construct($plugin)
    {
        parent::__construct();

        $this['plugin'] = $plugin;
        $this['plugin_display_name'] = \Config::get()->getValue(\Mooc\PLUGIN_DISPLAY_NAME_ID);

        $this->setupEnv();
        $this->setupBlockStuff();
        $this->setupCoursewareStuff();
    }

    private function setupEnv()
    {
        $this['current_user_id'] = isset($GLOBALS['user']) ? $GLOBALS['user']->id : 'nobody';

        $this['current_user'] = function ($c) {
            $user = new User($c, $c['current_user_id']);
            if ($user->isNew()) {
                // TODO: mlunzena: create a nobody user
            }
            return $user;
        };


        $this['cid'] = \Request::option('cid') ?: $GLOBALS['SessionSeminar'];

        $this['datafields'] = array(
            'preview_image' => md5('(M)OOC-Preview-Image'),
            'preview_video' => md5('(M)OOC-Preview-Video (mp4)'),
            'start'         => md5('(M)OOC Startdatum'),
            'duration'      => md5('(M)OOC Dauer'),
            'hint'          => md5('(M)OOC Hinweise')
        );
    }


    private function setupCoursewareStuff()
    {
        $this['courseware_factory'] = function ($c) {
            return new CoursewareFactory($c);
        };
    }

    private function setupBlockStuff()
    {
        $this['block_factory'] = function ($c) {
            return new BlockFactory($c);
        };

        $this['block_renderer'] = function ($c) {
            return new MustacheRenderer($c);
        };

        $this['block_renderer_helpers'] = $this->getMustacheHelpers();
    }


    private function getMustacheHelpers()
    {
        $c = $this;
        return array(

            'i18n' => function ($text) { return _($text); },

            'plugin_url' => function ($text, $helper) use ($c) {
                return \PluginEngine::getURL($c['plugin'], array(), $helper->render($text));
            }
        );
    }
}
