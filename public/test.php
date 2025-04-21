<?php
// Test PHP functionality
$test_message = "PHP is working!";
$current_time = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Handling Test</title>
    
    <!-- Internal CSS -->
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .test-box {
            border: 2px solid #333;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>File Handling Test Page</h1>
    
    <!-- PHP Test Section -->
    <div class="test-box">
        <h2>PHP Test</h2>
        <p>Message: <span class="success"><?php echo $test_message; ?></span></p>
        <p>Current Time: <span class="success"><?php echo $current_time; ?></span></p>
    </div>

    <!-- CSS Test Section -->
    <div class="test-box">
        <h2>CSS Test</h2>
        <p>If you see this text in a styled box with a border, CSS is working!</p>
        <p>This text should be in a styled container with proper spacing and formatting.</p>
    </div>

    <!-- JavaScript Test Section -->
    <div class="test-box">
        <h2>JavaScript Test</h2>
        <button onclick="testJavaScript()">Click to Test JavaScript</button>
        <p id="js-result"></p>
    </div>

    <!-- External File Test Section -->
    <div class="test-box">
        <h2>External File Test</h2>
        <p>Testing external CSS and JavaScript files:</p>
        <div id="external-test"></div>
    </div>

    <!-- JavaScript for testing -->
    <script>
        // Internal JavaScript test
        function testJavaScript() {
            const result = document.getElementById('js-result');
            result.innerHTML = '<span class="success">JavaScript is working!</span>';
            result.style.display = 'block';
        }

        // Test external file loading
        window.addEventListener('DOMContentLoaded', function() {
            const externalTest = document.getElementById('external-test');
            
            // Test CSS file
            const cssLink = document.querySelector('link[href="css/test.css"]');
            if (cssLink) {
                externalTest.innerHTML += '<p class="success">✓ External CSS file loaded successfully</p>';
            } else {
                externalTest.innerHTML += '<p class="error">✗ External CSS file failed to load</p>';
            }

            // Test JavaScript file
            const jsScript = document.querySelector('script[src="js/test.js"]');
            if (jsScript) {
                externalTest.innerHTML += '<p class="success">✓ External JavaScript file loaded successfully</p>';
            } else {
                externalTest.innerHTML += '<p class="error">✗ External JavaScript file failed to load</p>';
            }
        });
    </script>

    <!-- External CSS -->
    <link rel="stylesheet" href="css/test.css">
    
    <!-- External JavaScript -->
    <script src="js/test.js"></script>
</body>
</html> 