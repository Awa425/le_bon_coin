export interface User {
  id: string;
  name: string;
  email: string;
  email_verified: boolean;
  phone: string | null;
  created_at: string | null;
}

export interface AuthResponse {
  data: User;
  token: string;
  token_type: 'Bearer';
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string | null;
}
