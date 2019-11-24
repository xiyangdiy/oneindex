<?php

namespace App\Http\Controllers;

use App\Helpers\OneDrive;
use App\Helpers\Tool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * OneDriveGraph 索引
 * Class IndexController
 *
 * @package App\Http\Controllers
 */
class IndexController extends Controller
{

    /**
     * 缓存超时时间 建议10分钟以下，否则会导致资源失效
     *
     * @var int|mixed|string
     */
    public $expires = 10;

    /**
     * 根目录
     *
     * @var mixed|string
     */
    public $root = '/';

    /**
     * 展示文件数组
     *
     * @var array
     */
    public $show = [];

    /**
     * IndexController constructor.
     */
    public function __construct()
    {
        $this->middleware(['checkInstall', 'checkToken', 'handleIllegalFile']);
        $this->expires = Tool::config('expires', 10);
        $this->root = Tool::config('root', '/');
        $this->show = [
            'stream' => explode(' ', Tool::config('stream')),
            'image'  => explode(' ', Tool::config('image')),
            'video'  => explode(' ', Tool::config('video')),
            'dash'   => explode(' ', Tool::config('dash')),
            'audio'  => explode(' ', Tool::config('audio')),
            'code'   => explode(' ', Tool::config('code')),
            'doc'    => explode(' ', Tool::config('doc')),
        ];
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \ErrorException
     */
    public function home(Request $request)
    {
        return $this->list($request);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \ErrorException
     */
    public function list(Request $request)
    {
        $realPath = $request->route()->parameter('query') ?? '/';
        $graphPath = Tool::getOriginPath($realPath);
        $queryPath = trim(Tool::getAbsolutePath($realPath), '/');
        $origin_path = rawurldecode($queryPath);
        $path_array = $origin_path ? explode('/', $origin_path) : [];
        $item = Cache::remember( //todo:优化性能
            'one:file:'.$graphPath,
            $this->expires,
            function () use ($graphPath) {
                $response = OneDrive::getItemByPath($graphPath);
                if ($response['errno'] === 0) {
                    return $response['data'];
                } else {
                    Tool::showMessage($response['msg'], false);

                    return null;
                }
            }
        );
        if (is_null($item)) {
            Tool::showMessage('获取目录失败，请检查路径或稍后重试', false);

            return view(config('olaindex.theme').'message');
        }
        if (array_has($item, '@microsoft.graph.downloadUrl')) {
            return redirect()->away($item['@microsoft.graph.downloadUrl']);
        }
        // 获取列表
        $key = 'one:list:'.$graphPath;
        if (Cache::has($key)) {
            $origin_items = Cache::get($key);
        } else {
            $response = OneDrive::getChildrenByPath(
                $graphPath,
                '?select=
                id,eTag,name,size,lastModifiedDateTime,file,image,folder,@microsoft.graph.downloadUrl&expand=thumbnails'
            );
            if ($response['errno'] === 0) {
                $origin_items = $response['data'];
                Cache::put($key, $origin_items, $this->expires);
            } else {
                Tool::showMessage($response['msg'], false);

                return view(config('olaindex.theme').'message');
            }
        }
        $hasImage = Tool::hasImages($origin_items);
        // 过滤微软OneNote文件
        $origin_items = array_where($origin_items, function ($value) {
            return !array_has($value, 'package.type');
        });
        // 处理加密目录
        if (!Session::has('LogInfo')) {
            if (!empty($origin_items['.password'])) {
                $pass_id = $origin_items['.password']['id'];
                $pass_url
                    = $origin_items['.password']['@microsoft.graph.downloadUrl'];
                $key = 'password:'.$origin_path;
                if (Session::has($key)) {
                    $data = Session::get($key);
                    $password = Tool::getFileContent($pass_url, false);
                    if (strcmp($password, decrypt($data['password'])) !== 0
                        || time() > $data['expires']
                    ) {
                        Session::forget($key);
                        Tool::showMessage('密码已过期', false);

                        return view(
                            config('olaindex.theme').'password',
                            compact('origin_path', 'pass_id')
                        );
                    }
                } else {
                    return view(
                        config('olaindex.theme').'password',
                        compact('origin_path', 'pass_id')
                    );
                }
            }
        }
        // 过滤受限隐藏目录
        if (!empty($origin_items['.deny'])) {
            if (!Session::has('LogInfo')) {
                Tool::showMessage('目录访问受限，仅管理员可以访问！', false);

                return view(config('olaindex.theme').'message');
            }
        }
        // 处理 head/readme
        $head = array_key_exists('HEAD.md', $origin_items)
            ? Tool::markdown2Html(Tool::getFileContent($origin_items['HEAD.md']['@microsoft.graph.downloadUrl']))
            : '';
        $readme = array_key_exists('README.md', $origin_items)
            ? Tool::markdown2Html(Tool::getFileContent($origin_items['README.md']['@microsoft.graph.downloadUrl']))
            : '';
        if (!Session::has('LogInfo')) {
            $origin_items = array_except(
                $origin_items,
                ['README.md', 'HEAD.md', '.password', '.deny']
            );
        }
        $limit = $request->get('limit', 20);
        $items = Tool::paginate($origin_items, $limit);
        $data = compact(
            'items',
            'origin_items',
            'origin_path',
            'path_array',
            'head',
            'readme',
            'hasImage'
        );

        return view(config('olaindex.theme').'one', $data);
    }

    /**
     * 获取文件信息或缓存
     *
     * @param $realPath
     *
     * @return mixed
     */
    public function getFileOrCache($realPath)
    {
        $absolutePath = Tool::getAbsolutePath($realPath);
        $absolutePathArr = explode('/', $absolutePath);
        $absolutePathArr = array_where($absolutePathArr, function ($value) {
            return $value !== '';
        });
        $name = array_pop($absolutePathArr);
        $absolutePath = implode('/', $absolutePathArr);
        $listPath = Tool::getOriginPath($absolutePath);
        $list = Cache::get('one:list:'.$listPath, '');
        if ($list && array_key_exists($name, $list)) {
            return $list[$name];
        } else {
            $graphPath = Tool::getOriginPath($realPath);

            // 获取文件
            return Cache::remember(
                'one:file:'.$graphPath,
                $this->expires,
                function () use ($graphPath) {
                    $response = OneDrive::getItemByPath(
                        $graphPath,
                        '?select=
                        id,eTag,name,size,lastModifiedDateTime,file,image,@microsoft.graph.downloadUrl
                        &expand=thumbnails'
                    );
                    if ($response['errno'] === 0) {
                        return $response['data'];
                    } else {
                        return null;
                    }
                }
            );
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \ErrorException
     */
    public function show(Request $request)
    {
        $realPath = $request->route()->parameter('query') ?? '/';
        if ($realPath === '/') {
            return redirect()->route('home');
        }
        $file = $this->getFileOrCache($realPath);
        if (is_null($file) || array_has($file, 'folder')) {
            Tool::showMessage('获取文件失败，请检查路径或稍后重试', false);

            return view(config('olaindex.theme').'message');
        }
        $file['download'] = $file['@microsoft.graph.downloadUrl'];
        foreach ($this->show as $key => $suffix) {
            if (in_array($file['ext'], $suffix)) {
                $view = 'show.'.$key;
                // 处理文本文件
                if (in_array($key, ['stream', 'code'])) {
                    if ($file['size'] > 5 * 1024 * 1024) {
                        Tool::showMessage('文件过大，请下载查看', false);

                        return redirect()->back();
                    } else {
                        $file['content'] = Tool::getFileContent(
                            $file['@microsoft.graph.downloadUrl'],
                            false
                        );
                    }
                }
                // 处理缩略图
                if (in_array($key, ['image', 'dash', 'video'])) {
                    $file['thumb'] = array_get($file, 'thumbnails.0.large.url');
                }
                // dash视频流
                if ($key === 'dash') {
                    if (!strpos(
                        $file['@microsoft.graph.downloadUrl'],
                        "sharepoint.com"
                    )
                    ) {
                        return redirect()->away($file['download']);
                    }
                    $replace = str_replace(
                        "thumbnail",
                        "videomanifest",
                        $file['thumb']
                    );
                    $file['dash'] = $replace
                        ."&part=index&format=dash&useScf=True&pretranscode=0&transcodeahead=0";
                }
                // 处理微软文档
                if ($key === 'doc') {
                    $url = "https://view.officeapps.live.com/op/view.aspx?src="
                        .urlencode($file['@microsoft.graph.downloadUrl']);

                    return redirect()->away($url);
                }
                $origin_path = rawurldecode(
                    trim(Tool::getAbsolutePath($realPath), '/')
                );
                $path_array = $origin_path ? explode('/', $origin_path) : [];
                $data = compact('file', 'path_array', 'origin_path');

                return view(config('olaindex.theme').$view, $data);
            } else {
                $last = end($this->show);
                if ($last === $suffix) {
                    break;
                }
            }
        }

        return redirect()->away($file['download']);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function download(Request $request)
    {
        $realPath = $request->route()->parameter('query') ?? '/';
        if ($realPath === '/') {
            Tool::showMessage('下载失败，请检查路径或稍后重试', false);

            return view(config('olaindex.theme').'message');
        }
        $file = $this->getFileOrCache($realPath);
        if (is_null($file) || array_has($file, 'folder')) {
            Tool::showMessage('下载失败，请检查路径或稍后重试', false);

            return view(config('olaindex.theme').'message');
        }
        $url = $file['@microsoft.graph.downloadUrl'];

        return redirect()->away($url);
    }

    /**
     * @param $id
     * @param $size
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \ErrorException
     */
    public function thumb($id, $size)
    {
        $response = OneDrive::thumbnails($id, $size);
        if ($response['errno'] === 0) {
            $url = $response['data']['url'];
        } else {
            $url = 'https://i.loli.net/2018/12/04/5c05cd3086425.png';
        }

        return redirect()->away($url);
    }

    /**
     * @param $id
     * @param $width
     * @param $height
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \ErrorException
     */
    public function thumbCrop($id, $width, $height)
    {
        $response = OneDrive::thumbnails($id, 'large');
        if ($response['errno'] === 0) {
            $url = $response['data']['url'];
            @list($url, $tmp) = explode('&width=', $url);
            $url .= strpos($url, '?') ? '&' : '?';
            $thumb = $url."width={$width}&height={$height}";
        } else {
            $thumb = 'https://i.loli.net/2018/12/04/5c05cd3086425.png';
        }

        return redirect()->away($thumb);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function view(Request $request)
    {
        $realPath = $request->route()->parameter('query') ?? '/';
        if ($realPath === '/') {
            Tool::showMessage('获取失败，请检查路径或稍后重试', false);

            return view(config('olaindex.theme').'message');
        }
        $file = $this->getFileOrCache($realPath);
        if (is_null($file) || array_has($file, 'folder')) {
            Tool::showMessage('获取失败，请检查路径或稍后重试', false);

            return view(config('olaindex.theme').'message');
        }
        $download = $file['@microsoft.graph.downloadUrl'];

        return redirect()->away($download);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \ErrorException
     */
    public function search(Request $request)
    {
        $keywords = $request->get('keywords');
        if ($keywords) {
            $path = Tool::getEncodeUrl($this->root);
            $response = OneDrive::search($path, $keywords);
            if ($response['errno'] === 0) {
                // 过滤结果中的文件夹\过滤微软OneNote文件
                $items = array_where($response['data'], function ($value) {
                    return !array_has($value, 'folder')
                        && !array_has($value, 'package.type');
                });
            } else {
                Tool::showMessage('搜索失败', true);
                $items = [];
            }
        } else {
            $items = [];
        }
        $limit = $request->get('limit', 20);
        $items = Tool::paginate($items, $limit);

        return view(config('olaindex.theme').'search', compact('items'));
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \ErrorException
     */
    public function searchShow($id)
    {
        $response = OneDrive::itemIdToPath($id, Tool::config('root'));
        if ($response['errno'] === 0) {
            $originPath = $response['data']['path'];
            if (trim($this->root, '/') != '') {
                $path = str_after($originPath, $this->root);
            } else {
                $path = $originPath;
            }
        } else {
            Tool::showMessage('获取连接失败', false);
            $path = '/';
        }

        return redirect()->route('show', $path);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     * @throws \ErrorException
     */
    public function handlePassword()
    {
        $password = request()->get('password');
        $origin_path = decrypt(request()->get('origin_path'));
        $pass_id = decrypt(request()->get('pass_id'));
        $data = [
            'password' => encrypt($password),
            'expires'  => time() + (int)$this->expires * 60, // 目录密码过期时间
        ];
        Session::put('password:'.$origin_path, $data);
        $response = OneDrive::getItem($pass_id);
        if ($response['errno'] === 0) {
            $url = $response['data']['@microsoft.graph.downloadUrl'];
            $directory_password = Tool::getFileContent($url, false);
        } else {
            Tool::showMessage('获取文件夹密码失败', false);
            $directory_password = '';
        }
        if (strcmp($password, $directory_password) === 0) {
            return redirect()->route('home', Tool::getEncodeUrl($origin_path));
        } else {
            Tool::showMessage('密码错误', false);

            return view(
                config('olaindex.theme').'password',
                compact('origin_path', 'pass_id')
            );
        }
    }
}
