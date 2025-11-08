// lib/src/core/api/api_service.dart
// سرویس HTTP برای تماس با افزونهٔ وردپرس (register / check / validate)
import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiService {
  // آدرس پایه سرور — اگر خواستی تغییر بده
  static const String baseUrl = 'https://cofeclick.ir/wp-json/mizan/v1';

  // ثبت درخواست
  static Future<Map<String, dynamic>> register({
    required String email,
    required String firstName,
    required String lastName,
    required String username,
    required String phone,
    required String storeName,
    required String deviceHash,
  }) async {
    final uri = Uri.parse('$baseUrl/register');
    final body = {
      'email': email,
      'first_name': firstName,
      'last_name': lastName,
      'username': username,
      'phone': phone,
      'store_name': storeName,
      'device_hash': deviceHash,
    };
    try {
      final r = await http
          .post(
            uri,
            body: jsonEncode(body),
            headers: {'Content-Type': 'application/json'},
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(r.body) as Map<String, dynamic>;
    } catch (e) {
      return {'success': false, 'message': 'خطا در ارتباط با سرور: $e'};
    }
  }

  // بررسی وضعیت و دریافت license_token اگر فعال باشد
  static Future<Map<String, dynamic>> check({
    required String deviceHash,
    String? email,
  }) async {
    final uri = Uri.parse('$baseUrl/check');
    final body = {'device_hash': deviceHash};
    if (email != null && email.isNotEmpty) body['email'] = email;
    try {
      final r = await http
          .post(
            uri,
            body: jsonEncode(body),
            headers: {'Content-Type': 'application/json'},
          )
          .timeout(const Duration(seconds: 10));
      return jsonDecode(r.body) as Map<String, dynamic>;
    } catch (e) {
      return {'success': false, 'message': 'خطا در ارتباط با سرور: $e'};
    }
  }

  // اعتبارسنجی آنلاین JWT (اختیاری)
  static Future<Map<String, dynamic>> validate({
    required String licenseToken,
  }) async {
    final uri = Uri.parse('$baseUrl/validate');
    final body = {'license_token': licenseToken};
    try {
      final r = await http
          .post(
            uri,
            body: jsonEncode(body),
            headers: {'Content-Type': 'application/json'},
          )
          .timeout(const Duration(seconds: 8));
      return jsonDecode(r.body) as Map<String, dynamic>;
    } catch (e) {
      return {'success': false, 'message': 'خطا در ارتباط با سرور: $e'};
    }
  }
}
