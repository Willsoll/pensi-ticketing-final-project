using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Linq;
using System.Net.Sockets;
using System.Reflection;
using System.Text;
using System.Threading;

internal static class Program
{
    private static string _tempRoot = "";
    private static string _appSrc = "";
    private static string _installRoot = "";
    private static string _webRoot = "";
    private static string _appTarget = "";
    private static string _appFolder = "pensi-eventpro";
    private static string _dbPass = "";
    private static string _dbName = "db_tiket_event_pro";
    private static string _mysqlClient = "";
    private static string _mysqld = "";
    private static string _apache = "";
    private static string _mysqlArgs = "";
    private static bool _silent = false;
    private static bool _noStart = false;
    private static bool _dryRun = false;

    private static void Main(string[] args)
    {
        Console.OutputEncoding = Encoding.UTF8;
        Console.Title = "PENSI EVENTPRO Installer";
        try
        {
            ParseArgs(args);
            Banner();
            Console.WriteLine("  Menyiapkan paket installer...");
            ExtractPayload();

            if (_silent)
            {
                if (_installRoot == "") Fail("Mode silent membutuhkan --root=C:\\xampp");
                DetectStack(_installRoot);
            }
            else
            {
                string choice = Menu();
                if (choice == "Q") return;
                ResolveChoice(choice);
            }
            Install();
        }
        catch (Exception ex)
        {
            Error("Instalasi gagal: " + ex.Message);
            if (!_silent) Pause();
        }
    }

    private static void ParseArgs(string[] args)
    {
        foreach (string a in args)
        {
            if (a == "--silent") _silent = true;
            else if (a == "--no-start") _noStart = true;
            else if (a == "--dry-run") _dryRun = true;
            else if (a.StartsWith("--root=")) _installRoot = a.Substring(7).Trim('"');
            else if (a.StartsWith("--app-folder=")) _appFolder = a.Substring(13).Trim('"');
            else if (a.StartsWith("--db-pass=")) _dbPass = a.Substring(10);
            else if (a.StartsWith("--db-name=")) _dbName = a.Substring(10);
            else if (a == "--help")
            {
                Console.WriteLine("PENSI EVENTPRO installer");
                Console.WriteLine("  PENSI_EVENTPRO_Setup.exe");
                Console.WriteLine("  --silent --root=C:\\xampp [--db-pass=...] [--no-start] [--dry-run]");
                Environment.Exit(0);
            }
        }
    }

    private static void Banner()
    {
        try { Console.Clear(); } catch { }
        Console.ForegroundColor = ConsoleColor.DarkMagenta;
        Console.WriteLine("  ============================================================");
        Console.ForegroundColor = ConsoleColor.White;
        Console.WriteLine("        P E N S I   E V E N T P R O   —   I N S T A L L E R");
        Console.WriteLine("        Aplikasi Pemesanan Tiket Event (PAKET 7)");
        Console.ForegroundColor = ConsoleColor.DarkMagenta;
        Console.WriteLine("  ============================================================");
        Console.ResetColor();
        Console.WriteLine();
    }

    private static string Menu()
    {
        List<string> xampps = Existing(new string[] { "C:\\xampp", "D:\\xampp" });
        List<string> laragons = Existing(new string[] { "C:\\laragon", "D:\\laragon", Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), "laragon") });

        Console.WriteLine("  Pilih hosting lokal yang ingin dipasang:");
        Console.WriteLine();
        Console.WriteLine("   [1] XAMPP    " + (xampps.Count > 0 ? xampps[0] : "(tidak terdeteksi)"));
        Console.WriteLine("   [2] Laragon  " + (laragons.Count > 0 ? laragons[0] : "(tidak terdeteksi)"));
        Console.WriteLine("   [3] Path lain (manual)");
        Console.WriteLine("   [Q] Keluar");
        Console.WriteLine();
        Console.Write("  Pilihan: ");
        return (Console.ReadLine() ?? "Q").Trim().ToUpperInvariant();
    }

    private static List<string> Existing(string[] paths)
    {
        List<string> found = new List<string>();
        foreach (string p in paths) if (Directory.Exists(p)) found.Add(p);
        return found;
    }

    private static void ResolveChoice(string choice)
    {
        string root = "";
        if (choice == "1")
        {
            List<string> found = Existing(new string[] { "C:\\xampp", "D:\\xampp" });
            root = found.Count > 0 ? found[0] : AskPath("XAMPP");
        }
        else if (choice == "2")
        {
            List<string> found = Existing(new string[] { "C:\\laragon", "D:\\laragon", Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.UserProfile), "laragon") });
            root = found.Count > 0 ? found[0] : AskPath("Laragon");
        }
        else if (choice == "3")
        {
            root = AskPath("folder hosting");
        }
        else
        {
            throw new Exception("Pilihan tidak dikenal.");
        }
        DetectStack(root);
    }

    private static string AskPath(string label)
    {
        Console.WriteLine();
        Console.Write("  Masukkan path " + label + " (contoh C:\\xampp): ");
        string p = (Console.ReadLine() ?? "").Trim().Trim('"');
        if (p == "") throw new Exception("Path kosong.");
        return p;
    }

    private static void DetectStack(string root)
    {
        _installRoot = root.TrimEnd('\\');
        if (!Directory.Exists(_installRoot)) throw new Exception("Folder tidak ditemukan: " + _installRoot);

        string htdocs = Path.Combine(_installRoot, "htdocs");
        string www = Path.Combine(_installRoot, "www");

        if (Directory.Exists(htdocs))
        {
            _webRoot = htdocs;
            _mysqlClient = Path.Combine(_installRoot, "mysql", "bin", "mysql.exe");
            _mysqld = Path.Combine(_installRoot, "mysql", "bin", "mysqld.exe");
            _apache = Path.Combine(_installRoot, "apache", "bin", "httpd.exe");
            _mysqlArgs = "--defaults-file=" + Quote(Path.Combine(_installRoot, "mysql", "bin", "my.ini"));
        }
        else if (Directory.Exists(www))
        {
            _webRoot = www;
            _mysqlClient = FindFile(Path.Combine(_installRoot, "bin", "mysql"), "mysql.exe");
            if (_mysqlClient == "") _mysqlClient = FindFile(Path.Combine(_installRoot, "bin", "mysql"), "mariadb.exe");
            _mysqld = FindFile(Path.Combine(_installRoot, "bin", "mysql"), "mysqld.exe");
            _apache = FindFile(Path.Combine(_installRoot, "bin", "apache"), "httpd.exe");
            _mysqlArgs = "";
        }
        else
        {
            throw new Exception("Tidak menemukan folder htdocs (XAMPP) atau www (Laragon) di " + _installRoot);
        }

        if (_mysqlClient == "" || !File.Exists(_mysqlClient)) throw new Exception("mysql.exe tidak ditemukan. Pastikan XAMPP/Laragon terpasang lengkap.");
        if (_apache == "" || !File.Exists(_apache)) throw new Exception("httpd.exe tidak ditemukan. Pastikan Apache terpasang.");

        Console.WriteLine();
        Console.WriteLine("  Hosting : " + (_mysqlArgs == "" ? "Laragon" : "XAMPP"));
        Console.WriteLine("  Root    : " + _installRoot);
        Console.WriteLine("  Webroot : " + _webRoot);
        Console.WriteLine();
    }

    private static string FindFile(string dir, string name)
    {
        if (!Directory.Exists(dir)) return "";
        try
        {
            string[] files = Directory.GetFiles(dir, name, SearchOption.AllDirectories);
            return files.Length > 0 ? files[0] : "";
        }
        catch { return ""; }
    }

    private static void Install()
    {
        _appTarget = Path.Combine(_webRoot, _appFolder);
        Console.WriteLine("  Target aplikasi : " + _appTarget);
        Console.WriteLine("  Database        : " + _dbName + " (data lama akan ditimpa)");
        Console.WriteLine();
        if (!_silent)
        {
            Console.Write("  Lanjutkan instalasi? (Y/n): ");
            string ans = (Console.ReadLine() ?? "Y").Trim().ToLowerInvariant();
            if (ans == "n" || ans == "no") return;
        }

        Step(1, 5, "Menyalin file aplikasi...");
        if (Directory.Exists(_appTarget))
        {
            string backup = _appTarget + "_backup_" + DateTime.Now.ToString("yyyyMMdd_HHmmss");
            Console.WriteLine("     Folder lama dipindahkan ke: " + backup);
            Directory.Move(_appTarget, backup);
        }
        CopyDir(_appSrc, _appTarget);
        Directory.CreateDirectory(Path.Combine(_appTarget, "storage"));
        Directory.CreateDirectory(Path.Combine(_appTarget, "storage", "logs"));
        Directory.CreateDirectory(Path.Combine(_appTarget, "public", "uploads", "bukti"));
        Console.WriteLine("     OK — " + CountFiles(_appTarget) + " file disalin.");

        Step(2, 5, "Menyiapkan database...");
        if (_dryRun)
        {
            Console.WriteLine("     [dry-run] import database dilewati.");
        }
        else
        {
            EnsureDbPassword();
            string dump = Path.Combine(_appTarget, "database", "db_tiket_event_pro.sql");
            if (!File.Exists(dump)) throw new Exception("Dump database tidak ditemukan: " + dump);
            if (!ImportDump(dump)) throw new Exception("Import database gagal. Periksa password MySQL root.");
            Console.WriteLine("     OK — skema + data demo diimpor.");
        }

        Step(3, 5, "Menulis konfigurasi...");
        WriteConfig();
        Console.WriteLine("     OK — config.local.php dibuat.");

        Step(4, 5, "Menjalankan hosting...");
        if (_noStart || _dryRun)
        {
            Console.WriteLine("     [skip] start hosting dilewati.");
        }
        else
        {
            StartHosting();
        }

        Step(5, 5, "Selesai.");
        Console.WriteLine();
        Console.ForegroundColor = ConsoleColor.Green;
        Console.WriteLine("  ============================================================");
        Console.WriteLine("   INSTALASI BERHASIL");
        Console.WriteLine("  ============================================================");
        Console.ResetColor();
        string url = "http://localhost/" + _appFolder + "/public/";
        Console.WriteLine("   URL       : " + url);
        Console.WriteLine("   Admin     : admin.eo@eventpro.id / password123");
        Console.WriteLine("   Finance   : finance@eventpro.id / password123");
        Console.WriteLine("   Gate      : gate@eventpro.id / password123");
        Console.WriteLine("   Customer  : dewi@student.id / password123");
        Console.WriteLine();
        if (!_silent && !_noStart && !_dryRun)
        {
            try { Process.Start(new ProcessStartInfo(url) { UseShellExecute = true }); } catch { }
        }
        if (!_silent) Pause();
    }

    private static void Step(int n, int total, string text)
    {
        Console.WriteLine();
        Console.ForegroundColor = ConsoleColor.Cyan;
        Console.WriteLine("  [" + n + "/" + total + "] " + text);
        Console.ResetColor();
    }

    private static void EnsureDbPassword()
    {
        if (_silent) return;
        if (_dbPass == "")
        {
            Console.Write("  Password MySQL root (kosongkan jika default XAMPP/Laragon): ");
            _dbPass = ReadPassword();
            Console.WriteLine();
        }
        int tries = 0;
        while (!TestMysql() && tries < 3)
        {
            Error("Tidak bisa terhubung ke MySQL dengan user root.");
            Console.Write("  Coba password lain (kosong = tanpa password): ");
            _dbPass = ReadPassword();
            Console.WriteLine();
            tries++;
        }
    }

    private static string ReadPassword()
    {
        StringBuilder sb = new StringBuilder();
        ConsoleKeyInfo key;
        while ((key = Console.ReadKey(true)).Key != ConsoleKey.Enter)
        {
            if (key.Key == ConsoleKey.Backspace && sb.Length > 0) sb.Length--;
            else if (key.Key != ConsoleKey.Backspace && key.KeyChar != '\0') sb.Append(key.KeyChar);
        }
        return sb.ToString();
    }

    private static bool TestMysql()
    {
        string output, error;
        int code = Run(_mysqlClient, "-u root -e \"SELECT 1\"", out output, out error);
        return code == 0;
    }

    private static bool ImportDump(string path)
    {
        try
        {
            ProcessStartInfo psi = new ProcessStartInfo(_mysqlClient, "-u root --default-character-set=utf8mb4");
            psi.UseShellExecute = false;
            psi.CreateNoWindow = true;
            psi.RedirectStandardInput = true;
            psi.RedirectStandardOutput = true;
            psi.RedirectStandardError = true;
            psi.EnvironmentVariables["MYSQL_PWD"] = _dbPass;
            using (Process p = Process.Start(psi))
            {
                string sql = File.ReadAllText(path, Encoding.UTF8);
                using (StreamWriter sw = new StreamWriter(p.StandardInput.BaseStream, new UTF8Encoding(false)))
                {
                    sw.Write(sql);
                }
                string err = p.StandardError.ReadToEnd();
                p.StandardOutput.ReadToEnd();
                p.WaitForExit();
                if (p.ExitCode != 0)
                {
                    Error(err.Trim());
                    return false;
                }
                return true;
            }
        }
        catch (Exception ex)
        {
            Error(ex.Message);
            return false;
        }
    }

    private static void WriteConfig()
    {
        string php = "<?php\n// Dibuat otomatis oleh installer PENSI EVENTPRO\nreturn array(\n"
            + "    'host' => '127.0.0.1',\n"
            + "    'port' => 3306,\n"
            + "    'name' => '" + PhpEsc(_dbName) + "',\n"
            + "    'user' => 'root',\n"
            + "    'pass' => '" + PhpEsc(_dbPass) + "',\n"
            + ");\n";
        File.WriteAllText(Path.Combine(_appTarget, "config.local.php"), php, new UTF8Encoding(false));
    }

    private static string PhpEsc(string s)
    {
        return (s ?? "").Replace("\\", "\\\\").Replace("'", "\\'");
    }

    private static void StartHosting()
    {
        bool apacheUp = PortOpen(80);
        bool mysqlUp = PortOpen(3306);
        Console.WriteLine("     Apache : " + (apacheUp ? "sudah berjalan" : "menyalakan..."));
        Console.WriteLine("     MySQL  : " + (mysqlUp ? "sudah berjalan" : "menyalakan..."));

        if (!apacheUp)
        {
            try { StartProcess(_apache, "", Path.GetDirectoryName(_apache)); } catch (Exception ex) { Error("Apache: " + ex.Message); }
        }
        if (!mysqlUp && _mysqld != "" && File.Exists(_mysqld))
        {
            try { StartProcess(_mysqld, _mysqlArgs, Path.GetDirectoryName(_mysqld)); } catch (Exception ex) { Error("MySQL: " + ex.Message); }
        }

        for (int i = 0; i < 20; i++)
        {
            if (PortOpen(80) && PortOpen(3306)) break;
            Thread.Sleep(1000);
        }
        Console.WriteLine("     Status : Apache " + (PortOpen(80) ? "OK" : "BELUM AKTIF") + " · MySQL " + (PortOpen(3306) ? "OK" : "BELUM AKTIF"));
        if (!PortOpen(80))
        {
            Console.ForegroundColor = ConsoleColor.Yellow;
            Console.WriteLine("     Jika Apache belum aktif, buka XAMPP/Laragon lalu klik Start All.");
            Console.ResetColor();
        }
    }

    private static void StartProcess(string exe, string args, string workdir)
    {
        ProcessStartInfo psi = new ProcessStartInfo(exe, args);
        psi.UseShellExecute = false;
        psi.CreateNoWindow = true;
        psi.WorkingDirectory = workdir;
        Process.Start(psi);
    }

    private static bool PortOpen(int port)
    {
        try
        {
            using (TcpClient c = new TcpClient())
            {
                IAsyncResult ar = c.BeginConnect("127.0.0.1", port, null, null);
                if (!ar.AsyncWaitHandle.WaitOne(400)) return false;
                c.EndConnect(ar);
                return true;
            }
        }
        catch { return false; }
    }

    private static int Run(string exe, string args, out string stdout, out string stderr)
    {
        ProcessStartInfo psi = new ProcessStartInfo(exe, args);
        psi.UseShellExecute = false;
        psi.CreateNoWindow = true;
        psi.RedirectStandardOutput = true;
        psi.RedirectStandardError = true;
        psi.EnvironmentVariables["MYSQL_PWD"] = _dbPass;
        using (Process p = Process.Start(psi))
        {
            stdout = p.StandardOutput.ReadToEnd();
            stderr = p.StandardError.ReadToEnd();
            p.WaitForExit();
            return p.ExitCode;
        }
    }

    private static void CopyDir(string src, string dst)
    {
        Directory.CreateDirectory(dst);
        foreach (string dir in Directory.GetDirectories(src, "*", SearchOption.AllDirectories))
        {
            Directory.CreateDirectory(dir.Replace(src, dst));
        }
        foreach (string file in Directory.GetFiles(src, "*", SearchOption.AllDirectories))
        {
            string target = file.Replace(src, dst);
            string parent = Path.GetDirectoryName(target);
            if (!Directory.Exists(parent)) Directory.CreateDirectory(parent);
            File.Copy(file, target, true);
        }
    }

    private static int CountFiles(string dir)
    {
        try { return Directory.GetFiles(dir, "*", SearchOption.AllDirectories).Length; }
        catch { return 0; }
    }

    private static string Quote(string s) { return "\"" + s + "\""; }

    private static void ExtractPayload()
    {
        Assembly asm = Assembly.GetExecutingAssembly();
        Stream s = asm.GetManifestResourceStream("PensiPayload.zip");
        if (s == null) throw new Exception("Payload installer tidak ditemukan.");
        _tempRoot = Path.Combine(Path.GetTempPath(), "PensiSetup_" + Guid.NewGuid().ToString("N").Substring(0, 8));
        Directory.CreateDirectory(_tempRoot);
        using (ZipArchive za = new ZipArchive(s, ZipArchiveMode.Read))
        {
            foreach (ZipArchiveEntry entry in za.Entries)
            {
                string dest = Path.Combine(_tempRoot, entry.FullName.Replace('/', Path.DirectorySeparatorChar));
                if (entry.Name == "")
                {
                    Directory.CreateDirectory(dest);
                    continue;
                }
                string parent = Path.GetDirectoryName(dest);
                if (!Directory.Exists(parent)) Directory.CreateDirectory(parent);
                using (Stream input = entry.Open())
                using (FileStream output = File.Create(dest))
                {
                    input.CopyTo(output);
                }
            }
        }
        _appSrc = Path.Combine(_tempRoot, "app");
        if (!Directory.Exists(_appSrc)) throw new Exception("Payload aplikasi tidak lengkap.");
    }

    private static void Error(string msg)
    {
        Console.ForegroundColor = ConsoleColor.Red;
        Console.WriteLine("  [ERROR] " + msg);
        Console.ResetColor();
    }

    private static void Fail(string msg)
    {
        Error(msg);
        Environment.Exit(1);
    }

    private static void Pause()
    {
        Console.WriteLine();
        Console.Write("  Tekan Enter untuk keluar...");
        Console.ReadLine();
    }
}
