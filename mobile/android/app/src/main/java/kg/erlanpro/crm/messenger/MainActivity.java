package kg.erlanpro.crm.messenger;

import android.os.Bundle;
import androidx.core.view.WindowCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // Don't draw WebView under status/nav bars — fixes back button + composer overlap
        WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
    }
}
