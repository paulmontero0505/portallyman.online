// MariaDB devuelve columnas JSON como string (JSON es alias de LONGTEXT);
// MySQL 8 las devuelve ya parseadas. Normaliza ambos casos a objeto/array (o null).
export function parseJsonColumn(v) {
  if (v == null) return null;
  if (typeof v === 'string') {
    try {
      return JSON.parse(v);
    } catch {
      return null;
    }
  }
  return v;
}
