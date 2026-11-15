<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tnc_versions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->index();
            $table->string('title', 180);
            $table->string('scope', 32)->default('all')->index();
            $table->string('version', 32);
            $table->unsignedInteger('version_number')->default(1);
            $table->string('summary', 500)->nullable();
            $table->longText('content');
            $table->string('content_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'version_number']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('tnc_version_id')
                ->nullable()
                ->constrained('tnc_versions')
                ->nullOnDelete();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('accepted_tnc_version_id')
                ->nullable()
                ->constrained('tnc_versions')
                ->nullOnDelete();
            $table->string('accepted_tnc_version', 32)->nullable();
            $table->string('accepted_tnc_hash', 64)->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->string('accepted_user_agent', 500)->nullable();
        });

        Schema::table('audition_participants', function (Blueprint $table) {
            $table->foreignId('accepted_tnc_version_id')
                ->nullable()
                ->constrained('tnc_versions')
                ->nullOnDelete();
            $table->string('accepted_tnc_version', 32)->nullable();
            $table->string('accepted_tnc_hash', 64)->nullable();
            $table->string('accepted_ip', 45)->nullable();
            $table->string('accepted_user_agent', 500)->nullable();
        });

        $this->seedInitialDefaults();
    }

    public function down(): void
    {
        Schema::table('audition_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_tnc_version_id');
            $table->dropColumn([
                'accepted_tnc_version',
                'accepted_tnc_hash',
                'accepted_ip',
                'accepted_user_agent',
            ]);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_tnc_version_id');
            $table->dropColumn([
                'accepted_tnc_version',
                'accepted_tnc_hash',
                'accepted_ip',
                'accepted_user_agent',
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tnc_version_id');
        });

        Schema::dropIfExists('tnc_versions');
    }

    private function seedInitialDefaults(): void
    {
        $now = now();

        $defaults = [
            [
                'code' => 'team-competition-standard',
                'title' => 'Syarat & Ketentuan Kompetisi Tim',
                'scope' => 'team',
                'version' => '1.0',
                'version_number' => 1,
                'summary' => 'Saya menyatakan bahwa seluruh data tim dan pemain yang diisikan adalah benar, menyetujui regulasi teknis kompetisi {{event_name}}, serta memahami bahwa slot kuota hanya ditahan selama {{payment_deadline_hours}} jam hingga pembayaran diselesaikan.',
                'content' => '<h3>1. Kebenaran Data &amp; Dokumen Pemain</h3><p>Manajer dan perwakilan resmi tim menjamin bahwa seluruh data identitas tim, ofisial, dan daftar pemain yang didaftarkan pada kompetisi <strong>{{event_name}}</strong> adalah benar, sah, dan dapat dipertanggungjawabkan secara administratif maupun hukum.</p><h3>2. Regulasi Teknis &amp; Sportivitas</h3><p>Setiap tim wajib mematuhi regulasi pertandingan, batas kategori usia, ketentuan verifikasi daftar susunan pemain (screening), serta menjunjung tinggi nilai sportivitas selama seluruh rangkaian kompetisi berlangsung.</p><h3>3. Kuota Pendaftaran &amp; Batas Waktu Pembayaran</h3><p>Slot kuota tim bersifat sementara dan hanya ditahan selama <strong>{{payment_deadline_hours}} jam</strong> sejak pendaftaran dikirimkan. Apabila pembayaran belum diselesaikan dan diverifikasi hingga batas waktu berakhir, sistem berhak membebaskan kembali slot kuota secara otomatis.</p><h3>4. Kebijakan Pembatalan &amp; Perubahan Jadwal</h3><p>Biaya pendaftaran yang telah terverifikasi tunduk pada ketentuan refund resmi panitia pelaksana. Panitia berhak melakukan penyesuaian jadwal atau venue apabila terjadi keadaan kahar (force majeure) dengan pemberitahuan resmi kepada seluruh tim peserta.</p>',
            ],
            [
                'code' => 'individual-audition-waiver',
                'title' => 'Syarat & Ketentuan Peserta Audisi',
                'scope' => 'audition',
                'version' => '1.0',
                'version_number' => 1,
                'summary' => 'Saya selaku orang tua / wali sah menyatakan bahwa peserta yang didaftarkan dalam kondisi sehat jasmani dan rohani untuk mengikuti aktivitas fisik seleksi sepak bola {{event_name}}, serta membebaskan panitia pelaksana dari tuntutan hukum atas risiko cedera olahraga selama kegiatan berlangsung.',
                'content' => '<h3>1. Pernyataan Kesiapan Fisik &amp; Kesehatan</h3><p>Saya selaku orang tua / wali sah atau peserta dewasa menyatakan bahwa peserta yang didaftarkan pada program <strong>{{event_name}}</strong> berada dalam kondisi sehat jasmani dan rohani, serta tidak memiliki riwayat kondisi medis berat yang melarang aktivitas fisik intensitas tinggi di lapangan.</p><h3>2. Risiko Aktivitas Olahraga Lapangan</h3><p>Saya memahami bahwa kegiatan seleksi sepak bola melibatkan aktivitas fisik intensif dan kontak olahraga yang memiliki risiko cedera wajar. Informasi riwayat alergi atau catatan medis khusus telah disampaikan secara jujur pada formulir pendaftaran.</p><h3>3. Pembebasan Tanggung Jawab Hukum (Liability Waiver)</h3><p>Dengan penuh kesadaran tanpa paksaan, saya membebaskan panitia pelaksana, tim pelatih, pengelola venue, dan pihak penyelenggara dari tuntutan hukum perdata maupun pidana atas risiko cedera olahraga yang terjadi selama rangkaian kegiatan berlangsung, sepanjang pertolongan pertama medis (P3K) di lapangan telah diberikan sesuai standar panitia.</p><h3>4. Persetujuan Dokumentasi &amp; Ketentuan Kuota</h3><p>Peserta wajib menyelesaikan pembayaran maksimal <strong>{{payment_deadline_hours}} jam</strong> setelah pendaftaran apabila berbayar. Panitia berhak mendokumentasikan kegiatan evaluasi teknis untuk keperluan rekrutmen internal dan publikasi resmi kegiatan.</p>',
            ],
            [
                'code' => 'coaching-clinic-standard',
                'title' => 'Syarat & Ketentuan Peserta Coaching Clinic',
                'scope' => 'coaching_clinic',
                'version' => '1.0',
                'version_number' => 1,
                'summary' => 'Saya menyatakan seluruh data peserta adalah benar, siap mematuhi tata tertib sesi pelatihan {{event_name}}, serta menyetujui pernyataan kesiapan fisik dan pembebasan tanggung jawab medis standar kegiatan lapangan.',
                'content' => '<h3>1. Kepatuhan Tata Tertib &amp; Keselamatan</h3><p>Peserta <strong>{{event_name}}</strong> wajib hadir tepat waktu sesuai sesi pelatihan yang ditetapkan, mengenakan perlengkapan olahraga standar keselamatan (sepatu bola/futsal yang sesuai dan pelindung tulang kering jika diperlukan), serta mengikuti seluruh instruksi tim pelatih.</p><h3>2. Kesiapan Fisik &amp; Tanggung Jawab Medis</h3><p>Peserta (dan orang tua/wali apabila berusia di bawah umur) menyatakan peserta dalam kondisi sehat untuk mengikuti sesi latihan praktik maupun teori di lapangan, serta membebaskan panitia dari tuntutan atas risiko cedera olahraga wajar selama kegiatan berlangsung.</p><h3>3. Batas Pembayaran &amp; Kehadiran</h3><p>Reservasi kursi coaching clinic ditahan selama <strong>{{payment_deadline_hours}} jam</strong> hingga pembayaran terkonfirmasi. Ketidakhadiran tanpa pemberitahuan resmi tidak mengalihkan hak keikutsertaan kepada pihak lain tanpa persetujuan panitia.</p>',
            ],
        ];

        foreach ($defaults as $item) {
            DB::table('tnc_versions')->insert([
                'code' => $item['code'],
                'title' => $item['title'],
                'scope' => $item['scope'],
                'version' => $item['version'],
                'version_number' => $item['version_number'],
                'summary' => $item['summary'],
                'content' => $item['content'],
                'content_hash' => hash('sha256', $item['content']),
                'is_active' => true,
                'published_at' => $now,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};

