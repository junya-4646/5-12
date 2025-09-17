<?php
declare(strict_types=1);

// すべてのメディアが満たす共通インターフェイス
interface Media {
    public function play(): void;
    public function toArray(): array;
}

//　Musicメディア
final class Music implements Media {

    public function __construct(private string $songTitle) {}
    public function getSongTitle(): string {
        return $this->songTitle;
    }
    public function play(): void {
        echo "[Music] {$this->songTitle} を再生中\n";
    }
    public function toArray(): array {
        return ['type'=>'music','title'=>$this->songTitle]; 
    }
}

//　Videoメディア
class Video implements Media {
    
    public function __construct(private string $videoTitle) {}
    public function getVideoTitle(): string {
        return $this->videoTitle;
    }

    public function play(): void {
        echo "[Video] {$this->videoTitle} を再生中\n";
    }
    public function toArray(): array {
        return ['type'=>'video','title'=>$this->videoTitle];
    }
}

//　Podcastメディア
class Podcast implements Media {
    
    public function __construct(private string $episodeTitle) {}
    public function getEpisodeTitle(): string {
        return $this->episodeTitle;
    }

    public function play(): void {
        echo "[Podcast] [{$this->episodeTitle}] を再生中\n";
    }
    public function toArray(): array {
        return ['type'=>'podcast','title'=>$this->episodeTitle];
    }
}


// JSON関連

//　JSON復元用のファクトリ関数
function mediaFromArray(array $row): Media {
    return match (strtolower($row['type'] ?? '')) {
        'music' => new Music($row['title'] ?? ''),
        'video' => new Video($row['title'] ?? ''),
        'podcast' => new Podcast($row['title'] ?? ''),
        default => new Music($row['title'] ?? ''),
    };
}

// JSON保存復元関数
const PLAYLIST_FILE = __DIR__ . '/playlist.json';

/**
 * プレイリストをJSONから読み込む
 * @return Media[] 復元されたメディアオブジェクトの配列
 */
function loadPlaylist(): array {
    if (!file_exists(PLAYLIST_FILE)) return [];
    $json = file_get_contents(PLAYLIST_FILE);
    if ($json === false || $json === '') return [];
    $rows = json_decode($json, true) ?? [];
    $list = [];
    foreach ($rows as $r) $list[] = mediaFromArray($r);
    return $list;
}
/**
 * プレイリストをJSONファイルに保存する
 * @param MediaPlayer $player 保存対象のメディアプレイヤー
 */
function savePlaylist(MediaPlayer $player): void {
    $rows = [];
    foreach ($player->all() as $m) $rows[] = $m->toArray();
    file_put_contents(
        PLAYLIST_FILE,
        json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

// ===== 入力ヘルパ =====
function prompt(string $msg): string {
    if (function_exists('readline')) {
        $s = readline($msg);
        return $s === false ? '' : $s;
    }
    echo $msg;
    $line = fgets(STDIN);
    return $line === false ? '' : rtrim($line, "\r\n");
}


// 再生処理(メディアプレイヤー)
final class MediaPlayer {

    /** @param Media[] $queue */
    public function __construct(private array $queue = []) {}

    public function add(Media $m): void {
        $this->queue[] = $m;
    }

    /** @return Media[] */ 
    public function all(): array {
        return $this->queue;
    }

    public function count(): int {
        return count($this->queue);
    }

    // 最初から順に再生
    public function playAllInteractive(): void {

        if ($this->count() === 0) {
            echo "キューは空です。\n";
            return;
        }
        foreach ($this->queue as $i => $m) {
            echo "---- " . ($i + 1) . " / {$this->count()} ----\n";
            $m->play();
            if ($this->handlePlaybackMenu()) return;
        }
        echo "最後まで再生されました。\n";
    }

    // ランダムに再生
    public function playShuffleInteractive(): void {
        if ($this->count() === 0) {
            echo "キューが空です。\n";
            return;
        }

        $indices = range(0, $this->count() - 1);
        shuffle($indices);
        foreach ($indices as $k => $i) {
            echo "---- shuffle " . ($k + 1) . " / {$this->count()} ----\n";
            $this->queue[$i]->play();
            if ($this->handlePlaybackMenu()) return;
        }
        echo "最後まで再生されました。\n";
    }
    

    // 再生中メニュー
    private function handlePlaybackMenu(): bool {
        while (true) {
            echo "[1] 一時停止  [2] 次へ  [3] 最初に戻る\n";

            $line = $this->readLineCompat("選んでください: ");
            if ($line === null) { 
                return false;
            }

            $ans = strtolower(trim($line));
            if ($ans === '1') { 
                $this->readLineCompat("一時停止しました。Enterを押すと再開します: ");
            } elseif ($ans === '2') {
                return false; //次へ
            } elseif ($ans === '3') {
                return true; //Topへ
            } else {
                echo "1 / 2 / 3 のいずれかを入力してください。\n";
            }
        }
    }

    // readline() 非搭載環境（Windows等）向けフォールバック
    private function readLineCompat(string $prompt): ?string {
        if (function_exists('readline')) {
            $s = readline($prompt);
            return $s === false ? null : $s;
        }
        echo $prompt;
        $s = fgets(STDIN);
        return $s === false ? null : rtrim($s, "\r\n");
    }


}

// ==== 実行スクリプト ====
$player = new MediaPlayer(loadPlaylist()); // 起動時復元

// 初期データ (プレイリストが空なら投入)
if ($player->count() === 0) {
    $player->add(new Music('You Only Live Once'));
    $player->add(new Video('PHP 入門講座'));
    $player->add(new Podcast('聞き流し心理学'));
    savePlaylist($player);
}

echo "=== メディアプレイヤー ===\n";
while (true) {
    echo "\nメニュー\n";
    echo " 1) リストを追加(Add)\n";
    echo " 2) 順番に再生(Play All)\n";
    echo " 3) ランダムに再生(Shuffle)\n";
    echo " 4) 再生リスト一覧を表示(List)\n";
    echo " 0) 終了(Exit)\n";

    $com = strtolower(trim(prompt("Select: ")));
    if ($com === '0') {
        savePlaylist($player);
        echo "保存しました。終了します。\n";
        break;
    }

    if ($com === '1') { 
        $t = strtolower(trim(prompt("Type [m]usic / [v]ideo / [p]odcast: ")));
        $title = trim(prompt("Title: "));
        if ($title === '') {
            echo "タイトルが空です。\n";
            continue;
        }
        if ($t === 'm') {
            $player->add(new Music($title));
        } elseif ($t === 'v') {
            $player->add(new Video($title));
        } elseif ($t === 'p') {
            $player->add(new Podcast($title));
        } else {
            echo "不明なタイプです。\n";
            continue;
        }
        echo "追加しました。\n"; 
        savePlaylist($player); 
    } elseif ($com === '2') {
        $player->playAllInteractive();
    } elseif ($com === '3') {
        $player->playShuffleInteractive();
    } elseif ($com === '4') {
        $all = $player->all();
        if (!$all) {
            echo "再生リストは空です。\n";
            continue;
        }
        foreach ($all as $i => $m) {
            $label = $m instanceof Music ? 'Music'
                    : ($m instanceof Video ? 'Video'
                    : 'Podcast');
            $title = $m instanceof Music ? $m->getSongTitle()
                    : ($m instanceof Video ? $m->getVideoTitle()
                    :  ($m instanceof Podcast ? $m->getEpisodeTitle() : ''));
            echo sprintf("%2d. %-8s %s\n", $i+1, "[$label]", $title);
        }
    } else {
        echo "そのコマンドは無効です。\n";
    }
}
?>