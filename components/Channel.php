<?php namespace RainLab\Forum\Components;

use Auth;
use Request;
use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use RainLab\Forum\Models\TopicWatch;
use RainLab\Forum\Models\Topic as TopicModel;
use RainLab\Forum\Models\Channel as ChannelModel;
use RainLab\Forum\Models\Member as MemberModel;

class Channel extends ComponentBase
{

    private $member = null;
    private $channel = null;

    /**
     * @var Collection Topics cache for Twig access.
     */
    public $topics = null;

    const PARAM_SLUG = 'slug';

    public function componentDetails()
    {
        return [
            'name'        => 'Channel',
            'description' => 'Displays a list of posts belonging to a channel.'
        ];
    }

    public function defineProperties()
    {
        return [
            'memberPage' => [
                'title'       => 'Member Page',
                'description' => 'Page name to use for clicking on a member.',
                'type'        => 'dropdown'
            ],
            'topicPage' => [
                'title'       => 'Topic Page',
                'description' => 'Page name to use for clicking on a conversation topic.',
                'type'        => 'dropdown'
            ],
        ];
    }

    public function getPropertyOptions($property)
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->addCss('/plugins/rainlab/forum/assets/css/forum.css');

        $this->page['channel'] = $this->getChannel();
        return $this->prepareTopicList();
    }

    public function getChannel()
    {
        if ($this->channel !== null)
            return $this->channel;

        if (!$slug = $this->param(static::PARAM_SLUG))
            return null;

        return $this->channel = ChannelModel::whereSlug($slug)->first();
    }

    protected function prepareTopicList()
    {
        /*
         * If channel exists, load the topics
         */
        if ($channel = $this->getChannel()) {

            $currentPage = post('page');
            $searchString = trim(post('search'));
            $topics = TopicModel::make()->listFrontEnd($currentPage, 'updated_at', $channel->id, $searchString);

            $this->page['member'] = $this->member = MemberModel::getFromUser();
            if ($this->member)
                $topics = TopicWatch::setFlagsOnTopics($topics, $this->member);

            $this->page['topics'] = $this->topics = $topics;

            /*
             * Pagination
             */
            if ($topics) {
                $queryArr = [];
                if ($searchString) $queryArr['search'] = $searchString;
                $queryArr['page'] = '';
                $paginationUrl = Request::url() . '?' . http_build_query($queryArr);

                if ($currentPage > ($lastPage = $topics->getLastPage()) && $currentPage > 1)
                    return Redirect::to($paginationUrl . $lastPage);

                $this->page['paginationUrl'] = $paginationUrl;
            }
        }

        /*
         * Load the page links
         */
        $links = [
            'member' => $this->property('memberPage'),
            'topic' => $this->property('topicPage'),
        ];

        $this->page['forumLink'] = $links;
        $this->page['isGuest'] = !Auth::check();
    }

}