# Registro de cambios de sistemas Ajax

>**IMPORTANTE**
>
>Como recordatorio, si no hay información sobre la actualización es porque se trata solo de la actualización de la documentación, la traducción o el texto

# 03/10/2023

- Adición de un nuevo estado de alarma en caso de armado forzado (por ejemplo, cuando el equipo tiene un error pero se fuerza la activación de la alarma)
  Este nuevo estado está disponible en el comando de estado del concentrador y tiene el valor técnico "FORCED_ARM". Ahora se muestra un logotipo con un escudo parcialmente lleno en el widget en este modo para indicar claramente que la alarma está en servicio pero con fallas potenciales.
- Se revisó el mecanismo de búsqueda de actualizaciones de comandos para permitir una mayor flexibilidad. En un futuro próximo, esto debería permitir añadir
  más información sobre el equipo. Dependiendo del tiempo y del material disponible para la prueba.
- Se eliminó la capacidad de ajustar manualmente las ID lógicas en pedidos de equipos.
- Se eliminó la capacidad de agregar o eliminar manualmente pedidos de equipos.
- Preparativos para implementar un mecanismo para actualizar los controles del equipo durante la actualización del complemento. Esto permitirá eliminar comandos obsoletos pero también agregar nuevos comandos sin afectar al usuario final. (Esta parte está actualmente en desarrollo)
- Documentación actualizada

# 06/06/2023

- Agregar concentrador de fibra

# 23/08/2022

- Gestión de grupos añadida
- Compatibilidad mejorada con múltiples transmisores

# 06/09/2022

- Eliminación de la actualización automática de información cada hora para limitar la cantidad de llamadas a Ajax y evitar el exceso de cuota

# 21/02/2021

- Error solucionado con el protocolo SIA

# 01/05/2021

- Se solucionó un problema para Socket

# 01/04/2022

- Optimización de la instalación de dependencias
- Corrección de la gestión del color del equipo
- Adición de la cortina doble para exteriores
- Agregar interruptor de pared

# 11/12/2021

- Gestión del color de los módulos para mostrar la imagen correcta (es necesario rehacer una sincronización)
- Corrección de un problema en las entradas externas de DoorProtect (es necesario retirar el equipo y resincronizar)
- Se solucionó un problema con el demonio SIA
- Actualización de documentación

# 02/12/2021

- Se agregaron comandos de encendido / apagado para relés
- Adición de un demonio SIA para la recuperación local de cierta información (lea la documentación para la configuración)
- Adición de equipo compatible

# 19/08/2021

- Cambio aleatorio del cron de actualización para intentar corregir el problema "Ha superado el límite en 100 solicitudes por minuto"
