<?php

namespace App\Console\Commands\OneDrive;

use App\Helpers\Tool;
use App\Helpers\OneDrive;
use Illuminate\Console\Command;

class Find extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'od:find
                            {keywords : Keywords}
                            {--id= : id}
                            {--remote=/ : Query Path}
                            {--offset=0 : Start}
                            {--limit=20 : Length}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find Items';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws \ErrorException
     */
    public function handle()
    {
        $this->info('请稍等...');
        $this->call('od:refresh');
        $keywords = $this->argument('keywords');
        $remote = $this->option('remote');
        $offset = $this->option('offset');
        $length = $this->option('limit');
        if ($id = $this->option('id')) {
            $response = OneDrive::getItem($id);
        } else {
            $response = OneDrive::search($remote, $keywords);
        }
        $data = $response['errno'] === 0 ? $response['data'] : [];
        if (!$data) {
            $this->warn('Please try again later');
            exit;
        }
        if ($id = $this->option('id')) {
            $data = [$data];
        }
        $data = $this->format($data);
        $items = array_slice($data, $offset, $length);
        $headers = [];
        $this->line('total '.count($items));
        $this->table($headers, $items, 'compact');
    }

    /**
     * @param $data
     *
     * @return array
     * @throws \ErrorException
     */
    public function format($data)
    {
        $list = [];
        foreach ($data as $item) {
            $type = array_has($item, 'folder') ? 'd' : '-';
            $size = Tool::convertSize($item['size']);
            $time = date('M m H:i', strtotime($item['lastModifiedDateTime']));
            $folder = array_has($item, 'folder')
                ? array_get($item, 'folder.childCount')
                : '1';
            $owner = array_get($item, 'createdBy.user.displayName');
            if ($id = $this->option('id')) {
                $response = OneDrive::itemIdToPath($item['id']);
                $path = $response['errno'] === 0 ? $response['data']['path']
                    : 'Failed Fetch Path!';
                $content = [
                    $type,
                    $path,
                    $folder,
                    $owner,
                    $size,
                    $time,
                    $item['name'],
                ];
            } else {
                $content = [
                    $type,
                    $item['id'],
                    $folder,
                    $owner,
                    $size,
                    $time,
                    $item['name'],
                ];
            }
            $list[] = $content;
        }

        return $list;
    }
}
