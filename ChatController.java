package info.devexchanges.bluetoothchatapp;

import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothServerSocket;
import android.bluetooth.BluetoothSocket;
import android.content.Context;
import android.os.Bundle;
import android.os.Handler;
import android.os.Message;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.util.UUID;

public class ChatController {
    private static final String APP_NAME = "BluetoothChatApp";
    /* access modifiers changed from: private */
    public static final UUID MY_UUID = UUID.fromString("8ce255c0-200a-11e0-ac64-0800200c9a66");
    static final int STATE_CONNECTED = 3;
    static final int STATE_CONNECTING = 2;
    static final int STATE_LISTEN = 1;
    static final int STATE_NONE = 0;
    private AcceptThread acceptThread;
    /* access modifiers changed from: private */
    public final BluetoothAdapter bluetoothAdapter = BluetoothAdapter.getDefaultAdapter();
    /* access modifiers changed from: private */
    public ConnectThread connectThread;
    private ReadWriteThread connectedThread;
    /* access modifiers changed from: private */
    public final Handler handler;
    /* access modifiers changed from: private */
    public int state = 0;

    public ChatController(Context context, Handler handler2) {
        this.handler = handler2;
    }

    private synchronized void setState(int state2) {
        this.state = state2;
        this.handler.obtainMessage(1, state2, -1).sendToTarget();
    }

    public synchronized int getState() {
        return this.state;
    }

    public synchronized void start() {
        if (this.connectThread != null) {
            this.connectThread.cancel();
            this.connectThread = null;
        }
        if (this.connectedThread != null) {
            this.connectedThread.cancel();
            this.connectedThread = null;
        }
        setState(1);
        if (this.acceptThread == null) {
            this.acceptThread = new AcceptThread();
            this.acceptThread.start();
        }
    }

    public synchronized void connect(BluetoothDevice device) {
        if (this.state == 2 && this.connectThread != null) {
            this.connectThread.cancel();
            this.connectThread = null;
        }
        if (this.connectedThread != null) {
            this.connectedThread.cancel();
            this.connectedThread = null;
        }
        this.connectThread = new ConnectThread(device);
        this.connectThread.start();
        setState(2);
    }

    public synchronized void connected(BluetoothSocket socket, BluetoothDevice device) {
        if (this.connectThread != null) {
            this.connectThread.cancel();
            this.connectThread = null;
        }
        if (this.connectedThread != null) {
            this.connectedThread.cancel();
            this.connectedThread = null;
        }
        if (this.acceptThread != null) {
            this.acceptThread.cancel();
            this.acceptThread = null;
        }
        this.connectedThread = new ReadWriteThread(socket);
        this.connectedThread.start();
        Message msg = this.handler.obtainMessage(4);
        Bundle bundle = new Bundle();
        bundle.putParcelable(MainActivity.DEVICE_OBJECT, device);
        msg.setData(bundle);
        this.handler.sendMessage(msg);
        setState(3);
    }

    public synchronized void stop() {
        if (this.connectThread != null) {
            this.connectThread.cancel();
            this.connectThread = null;
        }
        if (this.connectedThread != null) {
            this.connectedThread.cancel();
            this.connectedThread = null;
        }
        if (this.acceptThread != null) {
            this.acceptThread.cancel();
            this.acceptThread = null;
        }
        setState(0);
    }

    public void write(byte[] out) {
        synchronized (this) {
            if (this.state == 3) {
                ReadWriteThread r = this.connectedThread;
                r.write(out);
            }
        }
    }

    /* access modifiers changed from: private */
    public void connectionFailed() {
        Message msg = this.handler.obtainMessage(5);
        Bundle bundle = new Bundle();
        bundle.putString("toast", "Unable to connect device");
        msg.setData(bundle);
        this.handler.sendMessage(msg);
        start();
    }

    /* access modifiers changed from: private */
    public void connectionLost() {
        Message msg = this.handler.obtainMessage(5);
        Bundle bundle = new Bundle();
        bundle.putString("toast", "Device connection was lost");
        msg.setData(bundle);
        this.handler.sendMessage(msg);
        start();
    }

    private class AcceptThread extends Thread {
        private final BluetoothServerSocket serverSocket;

        public AcceptThread() {
            BluetoothServerSocket tmp = null;
            try {
                tmp = ChatController.this.bluetoothAdapter.listenUsingInsecureRfcommWithServiceRecord(ChatController.APP_NAME, ChatController.MY_UUID);
            } catch (IOException ex) {
                ex.printStackTrace();
            }
            this.serverSocket = tmp;
        }

        public void run() {
            setName("AcceptThread");
            while (ChatController.this.state != 3) {
                try {
                    BluetoothSocket socket = this.serverSocket.accept();
                    if (socket != null) {
                        synchronized (ChatController.this) {
                            switch (ChatController.this.state) {
                                case 0:
                                case 3:
                                    try {
                                        socket.close();
                                        break;
                                    } catch (IOException e) {
                                        break;
                                    }
                                case 1:
                                case 2:
                                    ChatController.this.connected(socket, socket.getRemoteDevice());
                                    break;
                            }
                        }
                    }
                } catch (IOException e2) {
                    return;
                }
            }
        }

        public void cancel() {
            try {
                this.serverSocket.close();
            } catch (IOException e) {
            }
        }
    }

    private class ConnectThread extends Thread {
        private final BluetoothDevice device;
        private final BluetoothSocket socket;

        public ConnectThread(BluetoothDevice device2) {
            this.device = device2;
            BluetoothSocket tmp = null;
            try {
                tmp = device2.createInsecureRfcommSocketToServiceRecord(ChatController.MY_UUID);
            } catch (IOException e) {
                e.printStackTrace();
            }
            this.socket = tmp;
        }

        public void run() {
            setName("ConnectThread");
            ChatController.this.bluetoothAdapter.cancelDiscovery();
            try {
                this.socket.connect();
                synchronized (ChatController.this) {
                    ConnectThread unused = ChatController.this.connectThread = null;
                }
                ChatController.this.connected(this.socket, this.device);
            } catch (IOException e) {
                try {
                    this.socket.close();
                } catch (IOException e2) {
                }
                ChatController.this.connectionFailed();
            }
        }

        public void cancel() {
            try {
                this.socket.close();
            } catch (IOException e) {
            }
        }
    }

    private class ReadWriteThread extends Thread {
        private final BluetoothSocket bluetoothSocket;
        private final InputStream inputStream;
        private final OutputStream outputStream;

        public ReadWriteThread(BluetoothSocket socket) {
            this.bluetoothSocket = socket;
            InputStream tmpIn = null;
            OutputStream tmpOut = null;
            try {
                tmpIn = socket.getInputStream();
                tmpOut = socket.getOutputStream();
            } catch (IOException e) {
            }
            this.inputStream = tmpIn;
            this.outputStream = tmpOut;
        }

        public void run() {
            byte[] buffer = new byte[1024];
            while (true) {
                try {
                    ChatController.this.handler.obtainMessage(2, this.inputStream.read(buffer), -1, buffer).sendToTarget();
                } catch (IOException e) {
                    ChatController.this.connectionLost();
                    ChatController.this.start();
                    return;
                }
            }
        }

        public void write(byte[] buffer) {
            try {
                this.outputStream.write(buffer);
                ChatController.this.handler.obtainMessage(3, -1, -1, buffer).sendToTarget();
            } catch (IOException e) {
            }
        }

        public void cancel() {
            try {
                this.bluetoothSocket.close();
            } catch (IOException e) {
                e.printStackTrace();
            }
        }
    }
}
