# Änderungsprotokoll Ajax-Systeme

>**WICHTIG**
>
>Zur Erinnerung: Wenn keine Informationen zur Aktualisierung vorliegen, liegt dies daran, dass es sich nur um die Aktualisierung von Dokumentation, Übersetzung oder Text handelt

# 03/10/2023

- Hinzufügung eines neuen Alarmstatus im Falle einer erzwungenen Scharfschaltung (z. B. wenn ein Gerätefehler vorliegt, der Alarm jedoch zwangsweise aktiviert werden muss)
  Dieser neue Status ist über den Hub-Statusbefehl verfügbar und hat den technischen Wert „FORCED_ARM“. In diesem Modus wird jetzt auf dem Widget ein Logo mit einem teilweise ausgefüllten Schild angezeigt, um deutlich anzuzeigen, dass der Alarm in Betrieb ist, aber möglicherweise Fehler vorliegen
– Der Mechanismus zum Abrufen von Befehlsaktualisierungen wurde überarbeitet, um mehr Flexibilität zu ermöglichen. In naher Zukunft sollte dies eine Erweiterung ermöglichen
  Weitere Informationen zur Ausstattung. Abhängig von Zeit und Material, das zum Testen zur Verfügung steht
- Die Möglichkeit, logische IDs bei Ausrüstungsbestellungen manuell anzupassen, wurde entfernt
- Die Möglichkeit, Ausrüstungsbestellungen manuell hinzuzufügen oder zu entfernen, wurde entfernt
- Vorbereitungen für die Implementierung eines Mechanismus zur Aktualisierung der Gerätesteuerung während der Plugin-Aktualisierung. Dadurch können veraltete Befehle gelöscht, aber auch neue Befehle hinzugefügt werden, ohne dass sich dies auf den Endbenutzer auswirkt. (Dieser Teil befindet sich derzeit in der Entwicklung)
- Aktualisierte Dokumentation

# 06.06.2023

- Fasernabe hinzufügen

# 23.08.2022

- Gruppenverwaltung hinzugefügt
- Verbesserte Unterstützung für mehrere Sender

# 09.06.2022

- Entfernen der automatischen stündlichen Aktualisierung von Informationen, um die Anzahl der Aufrufe an Ajax zu begrenzen und eine Quotenüberschreitung zu verhindern

# 21.02.2021

- Fehler mit SIA-Protokoll behoben

# 01.05.2021

- Behebung eines Problems für Socket

# 01.04.2022

- Optimierung der Installation von Abhängigkeiten
- Korrektur des Farbmanagements der Geräte
- Ergänzung des Dual Curtain Outdoor
- Wandschalter hinzufügen

# 12.11.2021

- Farbmanagement der Module, um das richtige Bild anzuzeigen (eine Synchronisierung muss erneut durchgeführt werden)
- Behebung eines Problems an den externen Eingängen von DoorProtect (ein Ausbau des Gerätes und Neusynchronisation ist notwendig)
- Problem mit dem SIA-Daemon behoben
- Dokumentations-Update

# 12.02.2021

- Ein/Aus-Befehle für Relais hinzugefügt
- Hinzufügen eines SIA-Daemons zur lokalen Wiederherstellung bestimmter Informationen (lesen Sie die Dokumentation zur Konfiguration)
- Hinzufügen kompatibler Geräte

# 19.08.2021

- Zufällige Verschiebung des Aktualisierungscrons, um zu versuchen, das Problem zu beheben "Sie haben das Limit von 100 Anfragen pro Minute überschritten"
