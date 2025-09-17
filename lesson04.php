<?php
declare(strict_types=1);

//　親クラス：共通の基本クラス
class Person {
    private string $name;
    private int $age;

    public function __construct(string $name, int $age) {
        $this->name = $name;
        $this->age = $age;
    }

    public function getName(): string {
        return $this->name; 
    }
    public function getAge(): int {
        return $this->age;
    }

    public function getType(): string {
        return 'person';
    }
    // 共通の自己紹介
    public function introduce(): void {
        echo "私は[{$this->name}]です。年齢は[{$this->age}]歳です。\n";
    }

    // JSON保存用
    public function toArray(): array {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'age'  => $this->getAge(),
        ];
    }

    // JSON復元用
    public static function fromArray(array $row): self {
        return new self($row['name'], (int)$row['age']);
    }
}

//　子クラス：Teacher（Personクラス継承）
class Teacher extends Person {
    private string $subject;

    public function __construct(string $name, int $age, string $subject) {
        parent::__construct($name, $age);
        $this->subject = $subject;
    }

    public function getSubject(): string {
        return $this->subject;
    }
    public function getType(): string {
        return 'teacher';
    }

    // 親と同じシグネチャで上書き
    public function introduce(): void {
        echo "私は[" . $this->getName() . "]です。[" . $this->subject . "]を教えています。\n";
    }

    public function toArray(): array {
        $arr = parent::toArray();
        $arr['subject'] = $this->subject;
        return $arr;
    }

    public static function fromArray(array $row): self {
        return new self($row['name'], (int)$row['age'], $row['subject']);
    }
}

// 子クラス：Student（Person継承）
class Student extends Person{
    private string $studentId;


    public function __construct(string $name, int $age, ?string $studentId = null) {
        parent::__construct($name, $age);
        // 呼び出し側で渡されなければ仮に自動付与（'S0001' 形式）
        $this->studentId = $studentId ?? 'S0001';
    }

    public function getStudentId(): string { return $this->studentId; }
    public function getType(): string      { return 'student'; }

    public function introduce(): void {
        echo "私は[" . $this->getName() . "]です。学生IDは[" . $this->studentId . "]です。\n";
    }

    public function toArray(): array {
        $arr = parent::toArray();
        $arr['studentId'] = $this->studentId;
        return $arr;
    }

    public static function fromArray(array $row): self {
        return new self($row['name'], (int)$row['age'], $row['studentId']);
    }

    // 保存済みの配列から次の学生IDを採番（S0001, S0002, ...）
    public static function nextIdFrom(array $people): string {
        $max = 0;
        foreach ($people as $p) {
            if ($p instanceof self) {
                if (preg_match('/^S(\d{4,})$/', $p->getStudentId(), $m)) {
                    $max = max($max, (int)$m[1]);
                }
            }
        }
        return 'S' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    }


}


// JSON永続化
const DATA_FILE = __DIR__ . '/people.json';

function prompt(string $msg): string {
    echo $msg;
    $line = fgets(STDIN);
    return $line === false ? '' : rtrim($line, "\r\n");
}

function loadPeople(): array {
    if (!file_exists(DATA_FILE)) return [];
    $json = file_get_contents(DATA_FILE);
    if ($json === '' || $json === false) return [];
    $rows = json_decode($json, true) ?? [];
    $people = [];
    foreach ($rows as $row) {
        $type = $row['type'] ?? 'person';
        if ($type === 'teacher') {
            $obj = Teacher::fromArray($row);
        } elseif ($type === 'student') {
            $obj = Student::fromArray($row);
        } else {
            $obj = Person::fromArray($row);
        }
        $people[$obj->getName()] = $obj;
    }
    return $people;
}

function savePeople(array $people): void {
    $rows = [];
    foreach ($people as $p) {
        $rows[] = $p->toArray();
    }
    file_put_contents(
        DATA_FILE,
        json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}


// 昇格（promote）:Person->Teacher/Student
function maybePromote(Person $p, array &$people): void {
    $type = $p instanceof Teacher ?'teacher' : ($p instanceof Student ? 'student' : 'person');
    
    if ($type !== 'person') {
        $p->introduce();
        return;
    }

    $ans = strtolower(prompt("この人物に役割を付与しますか？ (t=教師 / s=学生 / n=しない) :"));
    if ($ans === 't') {
        $subject = prompt("教える科目：");
        $people[$p->getName()] = new Teacher($p->getName(), $p->getAge(), $subject);        
    } elseif ($ans === 's') {
        $id = Student::nextIdFrom($people);
        $people[$p->getName()] = new Student($p->getName(), $p->getAge(), $id);
        echo "学生IDを自動発行しました：[$id]\n";
    }
}


// ===== 実行ループ =====
$people = loadPeople();

echo "=== 学校の教育システム ===\n";
echo "名前を入力すると自己紹介をします。未登録ならPersonとして登録できます。\n";
echo "終了するには 'end' と入力してください。\n";

while (true) {
    $name = prompt("名前（endで終了）：");
    if ($name === '') continue;
    if (strtolower($name) === 'end') break;

    if (isset($people[$name])) {
        maybePromote($people[$name], $people);
        savePeople($people);
        continue;
    }

    // 新規登録：まずはPersonとして登録
    $age = (int)prompt("年齢：");
    $people[$name] = new Person($name, $age);
    savePeople($people);
    echo "登録しました（役割は未設定）。\n";
}

echo "\n保存して終了しました。\n";
?>