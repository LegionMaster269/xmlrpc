package info.devexchanges.bluetoothchatapp;

import android.app.Dialog;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.os.Bundle;
import android.os.Handler;
import android.os.Message;
import android.support.design.widget.TextInputLayout;
import android.support.v7.app.AppCompatActivity;
import android.util.Log;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.ListView;
import android.widget.TextView;
import android.widget.Toast;
import java.util.ArrayList;
import java.util.Set;

public class MainActivity extends AppCompatActivity {
    public static final String DEVICE_OBJECT = "device_name";
    public static final int MESSAGE_DEVICE_OBJECT = 4;
    public static final int MESSAGE_READ = 2;
    public static final int MESSAGE_STATE_CHANGE = 1;
    public static final int MESSAGE_TOAST = 5;
    public static final int MESSAGE_WRITE = 3;
    private static final int REQUEST_ENABLE_BLUETOOTH = 1;
    /* access modifiers changed from: private */
    public BluetoothAdapter bluetoothAdapter;
    /* access modifiers changed from: private */
    public Button btnConnect;
    /* access modifiers changed from: private */
    public ArrayAdapter<String> chatAdapter;
    private ChatController chatController;
    /* access modifiers changed from: private */
    public ArrayList<String> chatMessages;
    /* access modifiers changed from: private */
    public BluetoothDevice connectingDevice;
    /* access modifiers changed from: private */
    public Dialog dialog;
    /* access modifiers changed from: private */
    public ArrayAdapter<String> discoveredDevicesAdapter;
    private final BroadcastReceiver discoveryFinishReceiver = new BroadcastReceiver() {
        public void onReceive(Context context, Intent intent) {
            String action = intent.getAction();
            if ("android.bluetooth.device.action.FOUND".equals(action)) {
                BluetoothDevice device = (BluetoothDevice) intent.getParcelableExtra("android.bluetooth.device.extra.DEVICE");
                if (device.getBondState() != 12) {
                    MainActivity.this.discoveredDevicesAdapter.add(device.getName() + "\n" + device.getAddress());
                }
            } else if ("android.bluetooth.adapter.action.DISCOVERY_FINISHED".equals(action) && MainActivity.this.discoveredDevicesAdapter.getCount() == 0) {
                MainActivity.this.discoveredDevicesAdapter.add(MainActivity.this.getString(R.string.none_found));
            }
        }
    };
    private Handler handler = new Handler(new Handler.Callback() {
        public boolean handleMessage(Message msg) {
            switch (msg.what) {
                case 1:
                    switch (msg.arg1) {
                        case 0:
                        case 1:
                            MainActivity.this.setStatus("Not connected");
                            break;
                        case 2:
                            MainActivity.this.setStatus("Connecting...");
                            MainActivity.this.btnConnect.setEnabled(true);
                            break;
                        case 3:
                            MainActivity.this.setStatus("Connected to: " + MainActivity.this.connectingDevice.getName());
                            MainActivity.this.btnConnect.setEnabled(true);
                            break;
                    }
                case 2:
                    MainActivity.this.chatMessages.add(MainActivity.this.connectingDevice.getName() + ":  " + new String((byte[]) msg.obj, 0, msg.arg1));
                    MainActivity.this.chatAdapter.notifyDataSetChanged();
                    break;
                case 3:
                    MainActivity.this.chatMessages.add("Me: " + new String((byte[]) msg.obj));
                    MainActivity.this.chatAdapter.notifyDataSetChanged();
                    break;
                case 4:
                    BluetoothDevice unused = MainActivity.this.connectingDevice = (BluetoothDevice) msg.getData().getParcelable(MainActivity.DEVICE_OBJECT);
                    Toast.makeText(MainActivity.this.getApplicationContext(), "Connected to " + MainActivity.this.connectingDevice.getName(), 0).show();
                    break;
                case 5:
                    Toast.makeText(MainActivity.this.getApplicationContext(), msg.getData().getString("toast"), 0).show();
                    break;
            }
            return false;
        }
    });
    /* access modifiers changed from: private */
    public TextInputLayout inputLayout;
    private ListView listView;
    private TextView status;

    /* access modifiers changed from: protected */
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView((int) R.layout.activity_main);
        findViewsByIds();
        this.bluetoothAdapter = BluetoothAdapter.getDefaultAdapter();
        if (this.bluetoothAdapter == null) {
            Toast.makeText(this, "Bluetooth is not available!", 0).show();
            finish();
        }
        this.btnConnect.setOnClickListener(new View.OnClickListener() {
            public void onClick(View view) {
                MainActivity.this.showPrinterPickDialog();
            }
        });
        this.chatMessages = new ArrayList<>();
        this.chatAdapter = new ArrayAdapter<>(this, 17367043, this.chatMessages);
        this.listView.setAdapter(this.chatAdapter);
    }

    /* access modifiers changed from: private */
    public void showPrinterPickDialog() {
        this.dialog = new Dialog(this);
        this.dialog.setContentView(R.layout.layout_bluetooth);
        this.dialog.setTitle("Bluetooth Devices");
        if (this.bluetoothAdapter.isDiscovering()) {
            this.bluetoothAdapter.cancelDiscovery();
        }
        this.bluetoothAdapter.startDiscovery();
        ArrayAdapter<String> pairedDevicesAdapter = new ArrayAdapter<>(this, 17367043);
        this.discoveredDevicesAdapter = new ArrayAdapter<>(this, 17367043);
        ListView listView2 = (ListView) this.dialog.findViewById(R.id.pairedDeviceList);
        ListView listView22 = (ListView) this.dialog.findViewById(R.id.discoveredDeviceList);
        listView2.setAdapter(pairedDevicesAdapter);
        listView22.setAdapter(this.discoveredDevicesAdapter);
        registerReceiver(this.discoveryFinishReceiver, new IntentFilter("android.bluetooth.device.action.FOUND"));
        registerReceiver(this.discoveryFinishReceiver, new IntentFilter("android.bluetooth.adapter.action.DISCOVERY_FINISHED"));
        this.bluetoothAdapter = BluetoothAdapter.getDefaultAdapter();
        Set<BluetoothDevice> pairedDevices = this.bluetoothAdapter.getBondedDevices();
        if (pairedDevices.size() > 0) {
            for (BluetoothDevice device : pairedDevices) {
                pairedDevicesAdapter.add(device.getName() + "\n" + device.getAddress());
            }
        } else {
            pairedDevicesAdapter.add(getString(R.string.none_paired));
        }
        listView2.setOnItemClickListener(new AdapterView.OnItemClickListener() {
            public void onItemClick(AdapterView<?> adapterView, View view, int position, long id) {
                MainActivity.this.bluetoothAdapter.cancelDiscovery();
                String info2 = ((TextView) view).getText().toString();
                MainActivity.this.connectToDevice(info2.substring(info2.length() - 17));
                MainActivity.this.dialog.dismiss();
            }
        });
        listView22.setOnItemClickListener(new AdapterView.OnItemClickListener() {
            public void onItemClick(AdapterView<?> adapterView, View view, int i, long l) {
                MainActivity.this.bluetoothAdapter.cancelDiscovery();
                String info2 = ((TextView) view).getText().toString();
                Log.e("Data", "" + info2);
                MainActivity.this.connectToDevice(info2.substring(info2.length() - 17));
                MainActivity.this.dialog.dismiss();
            }
        });
        this.dialog.findViewById(R.id.cancelButton).setOnClickListener(new View.OnClickListener() {
            public void onClick(View v) {
                MainActivity.this.dialog.dismiss();
            }
        });
        this.dialog.setCancelable(false);
        this.dialog.show();
    }

    /* access modifiers changed from: private */
    public void setStatus(String s) {
        this.status.setText(s);
    }

    /* access modifiers changed from: private */
    public void connectToDevice(String deviceAddress) {
        this.bluetoothAdapter.cancelDiscovery();
        this.chatController.connect(this.bluetoothAdapter.getRemoteDevice(deviceAddress));
    }

    private void findViewsByIds() {
        this.status = (TextView) findViewById(R.id.status);
        this.btnConnect = (Button) findViewById(R.id.btn_connect);
        this.listView = (ListView) findViewById(R.id.list);
        this.inputLayout = (TextInputLayout) findViewById(R.id.input_layout);
        findViewById(R.id.btn_send).setOnClickListener(new View.OnClickListener() {
            public void onClick(View view) {
                if (MainActivity.this.inputLayout.getEditText().getText().toString().equals("")) {
                    Toast.makeText(MainActivity.this, "Please input some texts", 0).show();
                    return;
                }
                MainActivity.this.sendMessage(MainActivity.this.inputLayout.getEditText().getText().toString());
                MainActivity.this.inputLayout.getEditText().setText("");
            }
        });
    }

    public void onActivityResult(int requestCode, int resultCode, Intent data) {
        switch (requestCode) {
            case 1:
                if (resultCode == -1) {
                    this.chatController = new ChatController(this, this.handler);
                    return;
                }
                Toast.makeText(this, "Bluetooth still disabled, turn off application!", 0).show();
                finish();
                return;
            default:
                return;
        }
    }

    /* access modifiers changed from: private */
    public void sendMessage(String message) {
        if (this.chatController.getState() != 3) {
            Toast.makeText(this, "Connection was lost!", 0).show();
        } else if (message.length() > 0) {
            this.chatController.write(message.getBytes());
        }
    }

    public void onStart() {
        super.onStart();
        if (!this.bluetoothAdapter.isEnabled()) {
            startActivityForResult(new Intent("android.bluetooth.adapter.action.REQUEST_ENABLE"), 1);
        } else {
            this.chatController = new ChatController(this, this.handler);
        }
    }

    public void onResume() {
        super.onResume();
        if (this.chatController != null && this.chatController.getState() == 0) {
            this.chatController.start();
        }
    }

    public void onDestroy() {
        super.onDestroy();
        if (this.chatController != null) {
            this.chatController.stop();
        }
    }
}
