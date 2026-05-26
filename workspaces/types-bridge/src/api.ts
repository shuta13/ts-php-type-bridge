/**
 * Query parameters accepted by the GET /api/hello endpoint.
 * This type is shared with the PHP server via the type bridge pipeline.
 */
export type GetParam = 'greeting' | 'name';

/**
 * Keys accepted in FormData POST requests to /api/upload.
 * Synced to PHP $key annotations via the type bridge pipeline.
 */
export type PostFormDataKey = 'file' | 'label';

/**
 * Shape of the JSON body for POST /api/data.
 * Keys and value types are both synced to PHP via the type bridge pipeline.
 */
export type PostJsonBody = {
  message: string;
  count: number;
};

/**
 * Keys expected in the JSON body for POST /api/data.
 * Derived from PostJsonBody — synced to PHP $key annotations via the type bridge pipeline.
 */
export type PostJsonKey = keyof PostJsonBody;
