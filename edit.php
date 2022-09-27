<?php

// データベースの接続情報
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'board');

// タイムゾーンの設定
date_default_timezone_set('Asia/Tokyo');

// 変数の初期化
$view_name = null;
$message = array();
$message_data = null;
$error_message = array();
$pdo = null;
$stmt = null;
$res = null;
$option = null;


session_start();

// 管理者としてログインしているか確認
if(empty($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true){
    header('Location: ./admin.php');
    exit;
}

// データベースに接続
try{
    $option = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // phpのバージョンが8.0.0以上なのでデフォルトでこの設定になっている
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,      // 初期値はtrue
    );
    $pdo = new PDO('mysql:charset=UTF8;dbname='.DB_NAME.';host='.DB_HOST , DB_USER, DB_PASS, $option);
} catch(PDOException $e){
    // 接続エラーのときのエラー内容を取得
    $error_message[] = $e->getMessage();
}

if(!empty($_GET['message_id']) && empty($_POST['message_id'])){
    // SQL作成
    $stmt = $pdo->prepare('SELECT * FROM message WHERE id = :id');

    // 値をセット
    $stmt->bindValue(':id', $_GET['message_id'], PDO::PARAM_INT);

    // SQLクリエの実行
    $stmt->execute();

    // 表示するデータを取得
    $message_data = $stmt->fetch();

    // 投稿データが取得できない場合は管理ページに戻る
    if(empty($message_data)){
        header('Location: ./admin.php');
        exit;
    } 

} elseif(!empty($_POST['message_id'])){
    // 空白除去
    $view_name = preg_replace('/\A[\p{C}\p{Z}]++|[\p{C}\p{Z}]++\z/u', '', $_POST['view_name']);
    $message = preg_replace('/\A[\p{C}\p{Z}]++|[\p{C}\p{Z}]++\z/u', '', $_POST['message']);

    // 表示名の入力チェック
    if(empty($view_name)){
        $error_message[] = '表示名を入力してください。';
    } 

    // メッセージの入力チェック
    if(empty($message)){
        $error_message[] = 'ひと言メッセージを入力してください。';
    } else{
        if(100 < mb_strlen($message, 'UTF-8')){
            $error_message[] = 'ひと言メッセージは100文字以内で入力してください。';
        }
    }

    if(empty($error_message)){
        // トランザクション開始
        $pdo->beginTransaction();

        try{
            // SQL作成
            $stmt = $pdo->prepare('UPDATE message SET view_name = :view_name, message= :message WHERE id = :id');

            // 値をセット
            $stmt->bindParam(':view_name', $view_name, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindValue(':id', $_POST['message_id'], PDO::PARAM_INT);

            // SQLクリエの実行
            $stmt->execute();

            // コミット
            $res = $pdo->commit();

        } catch(Exception $e){
            // エラーが発生した場合はロールバック
            $pdo->rollBack();
        }

        // 更新に成功したら一覧に戻る
        if($res){
            header('Location: ./admin.php');
            exit;
        }
    }
}


// データベースの接続を閉じる
$stmt = null;
$pdo = null;

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="./style/style.css">
    <title>ひと言掲示板 管理ページ（投稿の編集）</title>
</head>
<body>
<h1>ひと言掲示板 管理ページ（投稿の編集）</h1>
<?php if(!empty($error_message)): ?>
    <ul class="error_message">
        <?php foreach($error_message as $value): ?>
            <li>・<?php echo $value; ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<!-- ここにメッセージの入力フォームを設置 -->
<form method="POST">
    <div>
        <label for="view_name">表示名</label>
        <input id="view_name" type="text" name="view_name" value="<?php if(!empty($message_data['view_name'])){
            echo $message_data['view_name'];
        } elseif(!empty($view_name)){
            echo htmlspecialchars($view_name, ENT_QUOTES, 'UTF-8');
        }?>">
    </div>
    <div>
        <label for="message">ひと言メッセージ</label>
        <textarea id="message" name="message"><?php if(!empty($message_data['message'])){
            echo $message_data['message'];
        } elseif(!empty($message)){
            echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }?></textarea>
    </div>
    <a class="btn_cancel" href="admin.php">キャンセル</a>
    <input type="submit" name="btn_submit" value="更新">
    <input type="hidden" name="message_id" value="<?php if(!empty($message_data['id'])){
        echo $message_data['id'];
    } elseif(!empty($_POST['message_id'])){
        echo htmlspecialchars($_POST['message_id'], ENT_QUOTES, 'UTF-8');
    }?>">
</form>
</body>
</html>