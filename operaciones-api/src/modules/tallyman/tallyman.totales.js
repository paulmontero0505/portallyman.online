// Totales del turno para los KPIs del relevo. Función pura (sin BD).
// El % global se calcula solo sobre las filas que tienen planned > 0:
// executed_de_esas_filas / planned_total * 100. Las filas sin planned
// cuentan para executed y n_actividades pero no distorsionan el %.
export function calcularTotales(regs) {
  let planned = 0, executed = 0, pending = 0, executedConPlan = 0;
  for (const r of regs) {
    const p = r.planned != null ? Number(r.planned) : null;
    const e = Number(r.executed) || 0;
    executed += e;
    if (p != null && p > 0) {
      planned += p;
      executedConPlan += Number(r.accumulated != null ? r.accumulated : e);
      if (r.pending != null) pending += Number(r.pending);
    }
  }
  const porcentaje = planned > 0
    ? Math.min(Math.round((executedConPlan / planned) * 1000) / 10, 100)
    : null;
  return {
    planned: Math.round(planned * 100) / 100,
    executed: Math.round(executed * 100) / 100,
    pending: Math.round(pending * 100) / 100,
    porcentaje,
    n_actividades: regs.length,
  };
}
