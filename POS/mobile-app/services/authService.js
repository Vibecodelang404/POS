import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_ENDPOINTS } from '../config/api';

class AuthService {
  async getLoginPublicKey() {
    const response = await axios.get(`${API_ENDPOINTS.LOGIN}?action=public_key`);

    if (!response.data?.success || !response.data?.public_key) {
      throw new Error(response.data?.message || 'Unable to load login public key');
    }

    return response.data.public_key;
  }

  base64ToArrayBuffer(base64) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
    let str = base64.replace(/=+$/, '');
    let output = [];
    let bc = 0;
    let bs;
    let buffer;
    let idx = 0;

    while ((buffer = str.charAt(idx++))) {
      buffer = chars.indexOf(buffer);
      if (buffer === -1) {
        continue;
      }

      bs = bc % 4 ? bs * 64 + buffer : buffer;
      if (bc++ % 4) {
        output.push(255 & (bs >> ((-2 * bc) & 6)));
      }
    }

    return new Uint8Array(output).buffer;
  }

  arrayBufferToBase64(buffer) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
    const bytes = new Uint8Array(buffer);
    let result = '';

    for (let i = 0; i < bytes.length; i += 3) {
      const byte1 = bytes[i];
      const byte2 = i + 1 < bytes.length ? bytes[i + 1] : 0;
      const byte3 = i + 2 < bytes.length ? bytes[i + 2] : 0;

      const triplet = (byte1 << 16) | (byte2 << 8) | byte3;

      result += chars[(triplet >> 18) & 0x3f];
      result += chars[(triplet >> 12) & 0x3f];
      result += i + 1 < bytes.length ? chars[(triplet >> 6) & 0x3f] : '=';
      result += i + 2 < bytes.length ? chars[triplet & 0x3f] : '=';
    }

    return result;
  }

  pemToArrayBuffer(pem) {
    const base64 = pem
      .replace(/-----BEGIN PUBLIC KEY-----/g, '')
      .replace(/-----END PUBLIC KEY-----/g, '')
      .replace(/\s+/g, '');

    return this.base64ToArrayBuffer(base64);
  }

  async encryptPassword(password) {
    if (!global.crypto?.subtle) {
      return null;
    }

    const publicKeyPem = await this.getLoginPublicKey();
    const publicKey = await global.crypto.subtle.importKey(
      'spki',
      this.pemToArrayBuffer(publicKeyPem),
      {
        name: 'RSA-OAEP',
        hash: 'SHA-1',
      },
      false,
      ['encrypt']
    );

    const encrypted = await global.crypto.subtle.encrypt(
      { name: 'RSA-OAEP' },
      publicKey,
      new TextEncoder().encode(password)
    );

    return this.arrayBufferToBase64(encrypted);
  }

  // Login user
  async login(username, password) {
    try {
      const formData = new FormData();
      formData.append('action', 'login');
      formData.append('username', username);
      const encryptedPassword = await this.encryptPassword(password);

      if (encryptedPassword) {
        formData.append('encrypted_password', encryptedPassword);
      } else {
        formData.append('password', password);
      }

      const response = await axios.post(API_ENDPOINTS.LOGIN, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.success) {
        // Store user data
        await AsyncStorage.setItem('user', JSON.stringify(response.data.user));
        await AsyncStorage.setItem('token', response.data.token || '');
        return { success: true, user: response.data.user };
      } else {
        return { success: false, message: response.data.message };
      }
    } catch (error) {
      console.error('Login error:', error);
      return { 
        success: false, 
        message: error.response?.data?.message || 'Network error. Please check your connection.' 
      };
    }
  }

  // Logout user
  async logout() {
    try {
      await AsyncStorage.removeItem('user');
      await AsyncStorage.removeItem('token');
      return { success: true };
    } catch (error) {
      console.error('Logout error:', error);
      return { success: false, message: 'Failed to logout' };
    }
  }

  // Get current user
  async getCurrentUser() {
    try {
      const userJson = await AsyncStorage.getItem('user');
      return userJson ? JSON.parse(userJson) : null;
    } catch (error) {
      console.error('Get user error:', error);
      return null;
    }
  }

  // Check if user is logged in
  async isAuthenticated() {
    const user = await this.getCurrentUser();
    return user !== null;
  }
}

export default new AuthService();
