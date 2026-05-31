import type { GetParam, PostFormDataKey, PostJsonKey } from 'type-bridge';

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:9999';

/**
 * GET /api/hello.php
 * Keys are constrained to GetParam from the shared type-bridge package.
 */
export async function apiGet(params: Partial<Record<GetParam, string>>): Promise<Response> {
  const searchParams = new URLSearchParams(params);
  return fetch(`${API_URL}/api/hello.php?${searchParams}`, {
    method: 'GET',
    mode: 'cors',
  });
}

/**
 * POST /api/upload.php with FormData
 * Keys are constrained to PostFormDataKey from the shared type-bridge package.
 */
export async function apiPostFormData(
  data: Partial<Record<PostFormDataKey, string | Blob>>,
): Promise<Response> {
  const formData = new FormData();
  for (const [key, value] of Object.entries(data)) {
    if (value !== undefined) {
      formData.append(key, value);
    }
  }
  return fetch(`${API_URL}/api/upload.php`, {
    method: 'POST',
    mode: 'cors',
    body: formData,
  });
}

/**
 * POST /api/data.php with JSON body
 * Keys are constrained to PostJsonKey from the shared type-bridge package.
 */
export async function apiPostJson(
  data: Partial<Record<PostJsonKey, unknown>>,
): Promise<Response> {
  return fetch(`${API_URL}/api/data.php`, {
    method: 'POST',
    mode: 'cors',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
}
