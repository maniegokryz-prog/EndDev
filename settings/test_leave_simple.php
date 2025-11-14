<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simple Leave API Test</title>
</head>
<body>
    <h2>Simple Leave API Test</h2>
    <button onclick="testAPI()">Test Get Dashboard Leaves</button>
    <pre id="result"></pre>

    <script>
        async function testAPI() {
            const resultDiv = document.getElementById('result');
            resultDiv.textContent = 'Loading...';
            
            try {
                const response = await fetch('../staffmanagement/api/leave_management.php?action=get_dashboard_leaves');
                
                // Get the response as text first to see what we're getting
                const text = await response.text();
                console.log('Response text:', text);
                
                // Try to parse as JSON
                try {
                    const data = JSON.parse(text);
                    resultDiv.textContent = JSON.stringify(data, null, 2);
                } catch (e) {
                    resultDiv.textContent = 'Parse Error: ' + e.message + '\n\nResponse:\n' + text;
                }
            } catch (error) {
                resultDiv.textContent = 'Network Error: ' + error.message;
            }
        }
    </script>
</body>
</html>
