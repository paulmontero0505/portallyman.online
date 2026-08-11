-- ════════════════════════════════════════════════════════════════════
-- ESTIBA_TURNO · 013 · Poblar DNI de colaboradores (match por codigo)
-- Requiere haber corrido antes 012_colaboradores_dni.sql (columna dni).
-- Ejecutar en cPanel / phpMyAdmin sobre la base portally_system.
-- ════════════════════════════════════════════════════════════════════

USE portally_system;

UPDATE colaboradores SET dni='75144696' WHERE codigo='0000154';
UPDATE colaboradores SET dni='72438398' WHERE codigo='0000153';
UPDATE colaboradores SET dni='70649933' WHERE codigo='0000160';
UPDATE colaboradores SET dni='71898513' WHERE codigo='0000155';
UPDATE colaboradores SET dni='72977549' WHERE codigo='0000157';
UPDATE colaboradores SET dni='70612774' WHERE codigo='0000130';
UPDATE colaboradores SET dni='70261734' WHERE codigo='0000156';
UPDATE colaboradores SET dni='70543184' WHERE codigo='0000158';
UPDATE colaboradores SET dni='76312588' WHERE codigo='0000152';
UPDATE colaboradores SET dni='46077768' WHERE codigo='0000267';
UPDATE colaboradores SET dni='77063453' WHERE codigo='0000276';
UPDATE colaboradores SET dni='72095849' WHERE codigo='0000289';
UPDATE colaboradores SET dni='72856714' WHERE codigo='0000293';
UPDATE colaboradores SET dni='47565149' WHERE codigo='0000290';
UPDATE colaboradores SET dni='72479622' WHERE codigo='0000291';
UPDATE colaboradores SET dni='75168666' WHERE codigo='0000306';
UPDATE colaboradores SET dni='72032923' WHERE codigo='0000307';
UPDATE colaboradores SET dni='72855482' WHERE codigo='0000299';
UPDATE colaboradores SET dni='73242848' WHERE codigo='0000300';
UPDATE colaboradores SET dni='72384512' WHERE codigo='0000301';
UPDATE colaboradores SET dni='74164857' WHERE codigo='0000305';
UPDATE colaboradores SET dni='75122938' WHERE codigo='0000308';
UPDATE colaboradores SET dni='76300212' WHERE codigo='0000303';
UPDATE colaboradores SET dni='76220039' WHERE codigo='0000304';
UPDATE colaboradores SET dni='71314975' WHERE codigo='0000297';
UPDATE colaboradores SET dni='70948132' WHERE codigo='0000326';
UPDATE colaboradores SET dni='75690664' WHERE codigo='0000336';
UPDATE colaboradores SET dni='73879906' WHERE codigo='0000350';
UPDATE colaboradores SET dni='76023193' WHERE codigo='0000351';
UPDATE colaboradores SET dni='75119444' WHERE codigo='0000382';
UPDATE colaboradores SET dni='77100134' WHERE codigo='0000389';
UPDATE colaboradores SET dni='76272498' WHERE codigo='0000379';
UPDATE colaboradores SET dni='72366409' WHERE codigo='0000384';
UPDATE colaboradores SET dni='77775856' WHERE codigo='0000386';
UPDATE colaboradores SET dni='75677794' WHERE codigo='0000380';
UPDATE colaboradores SET dni='44969221' WHERE codigo='0000403';
UPDATE colaboradores SET dni='71490895' WHERE codigo='0000404';
UPDATE colaboradores SET dni='70865089' WHERE codigo='0000410';
UPDATE colaboradores SET dni='71782631' WHERE codigo='0000428';
UPDATE colaboradores SET dni='74068074' WHERE codigo='0000555';
UPDATE colaboradores SET dni='76881652' WHERE codigo='0000561';
UPDATE colaboradores SET dni='78551693' WHERE codigo='0000583';
UPDATE colaboradores SET dni='75158105' WHERE codigo='0000582';
UPDATE colaboradores SET dni='47888406' WHERE codigo='0000579';
UPDATE colaboradores SET dni='72252626' WHERE codigo='0000580';
UPDATE colaboradores SET dni='77047236' WHERE codigo='0000653';
UPDATE colaboradores SET dni='73252271' WHERE codigo='0000654';
UPDATE colaboradores SET dni='77208907' WHERE codigo='0000656';
UPDATE colaboradores SET dni='74573633' WHERE codigo='0000657';
UPDATE colaboradores SET dni='75158388' WHERE codigo='0000658';
UPDATE colaboradores SET dni='74160747' WHERE codigo='0000652';
UPDATE colaboradores SET dni='72955694' WHERE codigo='0000746';
UPDATE colaboradores SET dni='74123630' WHERE codigo='0000747';
UPDATE colaboradores SET dni='74495166' WHERE codigo='0000745';
UPDATE colaboradores SET dni='74030457' WHERE codigo='0000744';
UPDATE colaboradores SET dni='72721201' WHERE codigo='0000748';
UPDATE colaboradores SET dni='75726584' WHERE codigo='0000769';
UPDATE colaboradores SET dni='72729776' WHERE codigo='0000770';
UPDATE colaboradores SET dni='72130941' WHERE codigo='0000771';
UPDATE colaboradores SET dni='73419027' WHERE codigo='0000772';
UPDATE colaboradores SET dni='45313076' WHERE codigo='0000773';
UPDATE colaboradores SET dni='72032942' WHERE codigo='0000622';
UPDATE colaboradores SET dni='73135580' WHERE codigo='0000576';
UPDATE colaboradores SET dni='76408786' WHERE codigo='0000143';
UPDATE colaboradores SET dni='71314986' WHERE codigo='0000493';
UPDATE colaboradores SET dni='72161308' WHERE codigo='0000533';
UPDATE colaboradores SET dni='72479599' WHERE codigo='0000225';
UPDATE colaboradores SET dni='48249558' WHERE codigo='0000577';

-- Verificación
-- SELECT codigo, dni, nombre FROM colaboradores WHERE dni IS NOT NULL ORDER BY codigo;
