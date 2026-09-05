public function up()
{
    Schema::table('rewards', function (Blueprint $table) {
        $table->decimal('unit_price', 10, 2)->default(0)->after('points_cost');
    });
}

public function down()
{
    Schema::table('rewards', function (Blueprint $table) {
        $table->dropColumn('unit_price');
    });
}