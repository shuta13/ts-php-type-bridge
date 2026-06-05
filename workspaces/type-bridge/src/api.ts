/**
 * Query parameters accepted by the GET /api/hello endpoint.
 * This type is shared with the PHP server via the type bridge pipeline.
 */
export type GetParam = 'greeting' | 'name';

/**
 * Shape of the FormData body for POST /api/upload.
 * Keys are synced to PHP $key annotations via the type bridge pipeline.
 */
export type PostFormDataBody = {
  file: File;
  label: string;
};

/**
 * Keys accepted in FormData POST requests to /api/upload.
 * Derived from PostFormDataBody — synced to PHP $key annotations via the type bridge pipeline.
 */
export type PostFormDataKey = keyof PostFormDataBody;

/**
 * Shape of the JSON body for POST /api/data.
 * Keys and value types are both synced to PHP via the type bridge pipeline.
 */
export type PostJsonBody = {
  message: string;
  count: number;
  author: {
    name: string;
    age: number;
  };
};

/**
 * Keys expected in the JSON body for POST /api/data.
 * Derived from PostJsonBody — synced to PHP $key annotations via the type bridge pipeline.
 */
export type PostJsonKey = keyof PostJsonBody;
