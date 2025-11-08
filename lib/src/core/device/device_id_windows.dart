// lib/src/core/device/device_id_windows.dart
// خواندن MachineGuid از رجیستری ویندوز و تولید device_hash (SHA256)
// اگر رجیستری در دسترس نبود، fallback به MAC address
import 'dart:convert';
import 'dart:io';
import 'package:crypto/crypto.dart';
import 'package:process_run/process_run.dart';

class DeviceId {
  // مقدار هش دستگاه (SHA256 رشتهٔ ترکیبی)
  static Future<String> getDeviceHash() async {
    String identifier = '';

    if (Platform.isWindows) {
      try {
        // اجرای دستور reg query برای خواندن MachineGuid
        final result = await run('reg', [
          'query',
          r'HKLM\SOFTWARE\Microsoft\Cryptography',
          '/v',
          'MachineGuid',
        ]);
        if (result.stdout != null) {
          final out = result.stdout.toString();
          final lines = out.split(RegExp(r'\r?\n'));
          for (var l in lines) {
            if (l.contains('MachineGuid')) {
              // خروجی معمول: MachineGuid    REG_SZ    xxxxxxx
              final parts = l.trim().split(RegExp(r'\s+'));
              if (parts.length >= 3) {
                identifier = parts.last;
                break;
              }
            }
          }
        }
      } catch (e) {
        // ignore و fallback به MAC
        identifier = '';
      }
    }

    // fallback: MAC addresses
    if (identifier.isEmpty) {
      try {
        final interfaces = await NetworkInterface.list(
          includeLoopback: false,
          type: InternetAddressType.IPv4,
        );
        for (var iface in interfaces) {
          if (iface.mac != null && iface.mac!.isNotEmpty) {
            identifier += iface.mac!;
          }
        }
      } catch (e) {
        identifier = identifier + Platform.localHostname;
      }
    }

    final bytes = utf8.encode(identifier);
    final digest = sha256.convert(bytes);
    return digest.toString();
  }
}
