<?php
/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 * @date 2026年11月14日
 *  生成sitemap文件
 */
namespace app\home\controller;

use core\basic\Controller;
use core\basic\Url;
use app\home\model\SitemapModel;

class SitemapController extends Controller
{

    const URL_LIMIT = 50000;

    protected $model;

    public function __construct()
    {
        $this->model = new SitemapModel();
    }

    public function index()
    {
        list($page, $plan) = $this->prepare();
        header("Content-type:text/xml;charset=utf-8");
        if (! $plan['indexed']) {
            $this->renderLegacyXml($plan['sorts']);
            return;
        }
        if ($page === 0) {
            $this->renderSitemapIndex($plan);
            return;
        }
        $this->renderUrlsetPage($page, $plan);
    }

    public function linkTxt()
    {
        list($page, $plan) = $this->prepare();
        header("Content-type:text/plain;charset=utf-8");
        if (! $plan['indexed']) {
            $this->renderLegacyTxt($plan['sorts']);
            return;
        }
        if ($page === 0) {
            $page = 1;
        }
        $this->renderTxtPage($page, $plan);
    }

    public static function pagePlan($fixed, $contentCount, $limit = self::URL_LIMIT)
    {
        $fixed = (int) $fixed;
        $contentCount = (int) $contentCount;
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = self::URL_LIMIT;
        }
        if ($fixed < 1) {
            $fixed = 1;
        }
        if ($contentCount < 0) {
            $contentCount = 0;
        }
        $total = $fixed + $contentCount;
        $indexed = $total > $limit;
        $firstContent = $limit - $fixed;
        if ($firstContent < 0) {
            $firstContent = 0;
        }
        $pages = 1;
        if ($indexed) {
            $rest = $contentCount - $firstContent;
            if ($rest < 0) {
                $rest = 0;
            }
            if ($rest > 0) {
                $pages += (int) ceil($rest / $limit);
            }
        }
        return array(
            'total' => $total,
            'indexed' => $indexed,
            'pages' => $pages,
            'first_content' => $firstContent,
            'limit' => $limit
        );
    }

    public static function contentWindow($page, array $plan)
    {
        $page = (int) $page;
        $limit = (int) $plan['limit'];
        $first = (int) $plan['first_content'];
        if ($page <= 1) {
            return array(0, $first);
        }
        return array($first + ($page - 2) * $limit, $limit);
    }

    public static function childLoc($page)
    {
        $page = (int) $page;
        if ($page < 1) {
            $page = 1;
        }
        $path = Url::addQuery(Url::home('sitemap.xml', false), 'page=' . $page);
        return htmlspecialchars(get_http_url() . $path, ENT_QUOTES, 'UTF-8');
    }

    private function prepare()
    {
        $page = $this->readPage();
        $plan = $this->buildPlan();
        if ($page > (int) $plan['pages']) {
            _404('您访问的页面不存在，请核对后重试！');
        }
        return array($page, $plan);
    }

    private function readPage()
    {
        if (! isset($_GET['page']) || $_GET['page'] === '') {
            return 0;
        }
        return intval(get('page', 'int'));
    }

    private function buildPlan()
    {
        $sorts = $this->model->getSorts();
        if (! is_array($sorts)) {
            $sorts = array();
        }
        $now = date('Y-m-d H:i:s');
        $contentCount = (int) $this->model->countOutputContent($now);
        $plan = self::pagePlan($this->countFixed($sorts), $contentCount, self::URL_LIMIT);
        $plan['sorts'] = $sorts;
        $plan['now'] = $now;
        return $plan;
    }

    private function countFixed(array $sorts)
    {
        $fixed = 1;
        foreach ($sorts as $value) {
            if ($value->outlink) {
                continue;
            }
            $fixed ++;
        }
        return $fixed;
    }

    private function renderSitemapIndex(array $plan)
    {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $lastmod = date('Y-m-d');
        $page = 1;
        $pages = (int) $plan['pages'];
        while ($page <= $pages) {
            echo '<sitemap>' . "\n";
            echo '    <loc>' . self::childLoc($page) . '</loc>' . "\n";
            echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            echo '</sitemap>' . "\n";
            $page ++;
        }
        echo '</sitemapindex>';
    }

    private function renderUrlsetPage($page, array $plan)
    {
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns= "http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $parser = new ParserController();
        if ((int) $page <= 1) {
            echo $this->escapedNode('', date('Y-m-d'), '1.00', 'always');
            $this->echoSortEntries($plan['sorts'], $parser, false);
        }
        $this->echoContentEntries($page, $plan, $parser, false);
        echo "\n</urlset>";
    }

    private function renderTxtPage($page, array $plan)
    {
        $parser = new ParserController();
        if ((int) $page <= 1) {
            echo get_http_url() . "\n";
            $this->echoSortEntries($plan['sorts'], $parser, true);
        }
        $this->echoContentEntries($page, $plan, $parser, true);
    }

    private function echoSortEntries(array $sorts, $parser, $asText)
    {
        foreach ($sorts as $value) {
            if ($value->outlink) {
                continue;
            } elseif ($value->type == 1) {
                $link = $parser->parserLink(1, $value->urlname, 'about', $value->scode, $value->filename);
            } else {
                $link = $parser->parserLink(2, $value->urlname, 'list', $value->scode, $value->filename);
            }
            if ($asText) {
                echo get_http_url() . $link . "\n";
            } else {
                echo $this->escapedNode($link, date('Y-m-d'), '0.80', 'daily');
            }
        }
    }

    private function echoContentEntries($page, array $plan, $parser, $asText)
    {
        list($offset, $count) = self::contentWindow($page, $plan);
        if ($count < 1) {
            return;
        }
        $rows = $this->model->getContentPage($plan['now'], $offset, $count);
        if (! $rows) {
            return;
        }
        foreach ($rows as $row) {
            $link = $parser->parserLink(2, $row->urlname, 'content', $row->scode, $row->sortfilename, $row->id, $row->filename);
            if ($asText) {
                echo get_http_url() . $link . "\n";
            } else {
                echo $this->escapedNode($link, date('Y-m-d', strtotime($row->date)), '0.60', 'daily');
            }
        }
    }

    private function escapedNode($link, $date, $priority = 0.60, $changefreq = 'always')
    {
        $loc = htmlspecialchars(get_http_url() . $link, ENT_QUOTES, 'UTF-8');
        return '
<url>
    <loc>' . $loc . '</loc>
    <priority>' . $priority . '</priority>
    <lastmod>' . $date . '</lastmod>
    <changefreq>' . $changefreq . '</changefreq>
</url>';
    }

    private function renderLegacyXml(array $sorts)
    {
        $str = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        //$str .= '<urlset>' . "\n";
        $str .= '<urlset xmlns= "http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" ;
        $str .= $this->makeNode('', date('Y-m-d'), '1.00', 'always'); // 根目录

        $Parser = new ParserController();
        foreach ($sorts as $value) {
            if ($value->outlink) {
                continue;
            } elseif ($value->type == 1) {
                $link = $Parser->parserLink(1, $value->urlname, 'about', $value->scode, $value->filename);
                $str .= $this->makeNode($link, date('Y-m-d'), '0.80', 'daily');
            } else {
                $link = $Parser->parserLink(2, $value->urlname, 'list', $value->scode, $value->filename);
                $str .= $this->makeNode($link, date('Y-m-d'), '0.80', 'daily');
                $contents = $this->model->getSortContent($value->scode);
                foreach ($contents as $value2) {
                    if ($value2->outlink) { // 外链
                        continue;
                    } else {
                        $link = $Parser->parserLink(2, $value2->urlname, 'content', $value2->scode, $value2->sortfilename, $value2->id, $value2->filename);
                    }
                    $str .= $this->makeNode($link, date('Y-m-d', strtotime($value2->date)), '0.60', 'daily');
                }
            }
        }
        echo $str . "\n</urlset>";
    }

    private function makeNode($link, $date, $priority = 0.60, $changefreq = 'always')
    {
        $node = '
<url>
    <loc>' . get_http_url() . $link . '</loc>
    <priority>' . $priority . '</priority>
    <lastmod>' . $date . '</lastmod>
    <changefreq>' . $changefreq . '</changefreq>
</url>';
        return $node;
    }

    private function renderLegacyTxt(array $sorts)
    {
        $Parser = new ParserController();
        $str = get_http_url() . "\n";
        foreach ($sorts as $value) {
            if ($value->outlink) {
                continue;
            } elseif ($value->type == 1) {
                $link = $Parser->parserLink(1, $value->urlname, 'about', $value->scode, $value->filename);
                $str .= get_http_url() . $link . "\n";
            } else {
                $link = $Parser->parserLink(2, $value->urlname, 'list', $value->scode, $value->filename);
                $str .= get_http_url() . $link . "\n";
                $contents = $this->model->getSortContent($value->scode);
                foreach ($contents as $value2) {
                    if ($value2->outlink) { // 外链
                        continue;
                    } else {
                        $link = $Parser->parserLink(2, $value2->urlname, 'content', $value2->scode, $value2->sortfilename, $value2->id, $value2->filename);
                    }
                    $str .= get_http_url() . $link . "\n";
                }
            }
        }
        echo $str;
    }
}
