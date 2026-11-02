const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

async function testScreenshot() {
    const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    const profileDir = path.resolve(__dirname, 'temp_profile_test');
    const port = 9333;

    const chrome = spawn(chromePath, [
        '--headless=new',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        `--remote-debugging-port=${port}`,
        `--user-data-dir=${profileDir}`,
        'about:blank'
    ]);

    // Wait for Chrome to start and fetch debugger endpoint
    let wsUrl = null;
    for (let i = 0; i < 30; i++) {
        await new Promise(r => setTimeout(r, 200));
        try {
            const res = await fetch(`http://127.0.0.1:${port}/json/version`);
            const data = await res.json();
            wsUrl = data.webSocketDebuggerUrl;
            if (wsUrl) break;
        } catch (e) {}
    }

    if (!wsUrl) {
        console.error('Failed to get WebSocketDebuggerUrl');
        chrome.kill();
        process.exit(1);
    }

    console.log('Connected to Chrome via CDP:', wsUrl);
    const ws = new WebSocket(wsUrl);

    let msgId = 1;
    const callbacks = new Map();

    ws.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id && callbacks.has(msg.id)) {
            const { resolve, reject } = callbacks.get(msg.id);
            callbacks.delete(msg.id);
            if (msg.error) reject(msg.error);
            else resolve(msg.result);
        }
    };

    function send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = msgId++;
            callbacks.set(id, { resolve, reject });
            ws.send(JSON.stringify({ id, method, params }));
        });
    }

    await new Promise(r => ws.onopen = r);

    // Create a new target/page
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });

    function sendPage(method, params = {}) {
        return send('Target.sendMessageToTarget', {
            sessionId,
            message: JSON.stringify({ id: msgId++, method, params })
        });
    }

    // Set viewport
    await send('Emulation.setDeviceMetricsOverride', {
        width: 1200,
        height: 800,
        deviceScaleFactor: 2,
        mobile: false
    });

    const targetUrl = 'file:///' + path.resolve(__dirname, 'test.html').replace(/\\/g, '/');
    await send('Page.navigate', { url: targetUrl });
    await new Promise(r => setTimeout(r, 1500)); // wait for mermaid render

    const result = await send('Page.captureScreenshot', { format: 'png' });
    const buffer = Buffer.from(result.data, 'base64');
    const outPath = path.resolve(__dirname, 'test_output.png');
    fs.writeFileSync(outPath, buffer);
    console.log('Successfully saved screenshot to:', outPath);

    ws.close();
    chrome.kill();
    process.exit(0);
}

testScreenshot().catch(e => {
    console.error(e);
    process.exit(1);
});
