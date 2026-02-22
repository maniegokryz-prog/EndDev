import urllib.request
import urllib.parse
import json

url = 'http://76.13.210.68/api/sync_endpoint.php'
data = urllib.parse.urlencode({'action': 'pull_updates'}).encode('utf-8')
req = urllib.request.Request(url, data=data)
req.add_header('X-API-KEY', 'lD9OcrtiWGxmSRCV1YpdqwAk5JPygLfo')

try:
    response = urllib.request.urlopen(req)
    result = json.loads(response.read().decode('utf-8'))
    print(json.dumps(result, indent=2))
except Exception as e:
    print(f"Error: {e}")
