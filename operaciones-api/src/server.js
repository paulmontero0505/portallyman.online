import 'dotenv/config';
import { createApp } from './app.js';
import { assertDbConnection } from './config/db.js';

const PORT = Number(process.env.PORT) || 4000;

(async () => {
  try {
    await assertDbConnection();
    console.log('✓ Conexión a MySQL OK');
  } catch (e) {
    console.error('✗ No se pudo conectar a MySQL:', e.message);
    process.exit(1);
  }
  createApp().listen(PORT, () => console.log(`✓ API Operaciones en http://localhost:${PORT}`));
})();
